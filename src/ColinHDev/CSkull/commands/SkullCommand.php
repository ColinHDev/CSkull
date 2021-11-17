<?php

namespace ColinHDev\CSkull\commands;

use ColinHDev\CSkull\DataProvider;
use ColinHDev\CSkull\items\Skull;
use ColinHDev\CSkull\ResourceManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\permission\Permission;
use pocketmine\player\Player;
use poggit\libasynql\SqlError;

class SkullCommand extends Command {

    public function __construct() {
        parent::__construct(
            ResourceManager::getInstance()->translateString("skull.name"),
            ResourceManager::getInstance()->translateString("skull.description"),
            ResourceManager::getInstance()->translateString("skull.usage"),
            json_decode(ResourceManager::getInstance()->translateString("skull.alias"), true)
        );
        $this->setPermission("cskull.command.skull");
        $this->setPermissionMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("skull.permissionMessage"));
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void {
        if (!$this->testPermission($sender)) {
            return;
        }
        if (!$sender instanceof Player) {
            $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("skull.notOnline"));
            return;
        }

        DataProvider::getInstance()->getLastCommandUseByUUID(
            $sender->getUniqueId()->toString(),
            function (array $rows) use ($sender, $args) : void {
                if ($sender->isOnline()) {
                    $lastCommandUseString = $rows[array_key_first($rows)]["lastCommandUse"];
                    if ($lastCommandUseString !== null) {
                        $lastCommandUse = \DateTime::createFromFormat("Y-m-d H:i:s", $lastCommandUseString)->getTimestamp();
                        $cooldown = $this->getCooldownOfPlayer($sender);
                        if ($cooldown === null) {
                            $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("skull.noCooldownSet"));
                            return;
                        }
                        if ($lastCommandUse + $cooldown > time()) {
                            $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("skull.onCooldown", [date(ResourceManager::getInstance()->translateString("skull.onCooldown.format"), ($lastCommandUse + $cooldown))]));
                            return;
                        }
                    }
                    $done = function (Player $sender, Skull $item, string $playerName) : void {
                        if (!$sender->isOnline()) {
                            return;
                        }
                        if (!$sender->getInventory()->canAddItem($item)) {
                            $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("skull.inventoryFull", [$playerName]));
                            return;
                        }
                        DataProvider::getInstance()->setLastCommandUse(
                            $sender->getUniqueId()->toString(),
                            date("Y-m-d H:i:s"),
                            function (int $affectedRows) use ($sender, $playerName, $item) : void {
                                if (!$sender->isOnline()) {
                                    return;
                                }
                                $sender->getInventory()->addItem($item);
                                if ($playerName === $sender->getName()) {
                                    $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("skull.success.ownSkull"));
                                } else {
                                    $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("skull.success", [$playerName]));
                                }
                            },
                            function (SqlError $error) use ($sender) : void {
                                if (!$sender->isOnline()) {
                                    return;
                                }
                                $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("skull.setLastCommandUseError", [$error->getMessage()]));
                            }
                        );
                    };
                    if (isset($args[0])) {
                        $playerName = $args[0];
                        $player = $sender->getServer()->getPlayerByPrefix($playerName);
                        if ($player === null) {
                            DataProvider::getInstance()->getPlayerByPrefix(
                                $playerName,
                                function (array $rows) use ($done, $sender, $playerName) : void {
                                    if (!$sender->isOnline()) {
                                        return;
                                    }
                                    // As done by Server::getPlayerByPrefix() we search the player whose name is the
                                    // most identical to our input string.
                                    $foundRow = null;
                                    $delta = PHP_INT_MAX;
                                    foreach ($rows as $row) {
                                        if (stripos($playerName, $row["playerName"]) === 0) {
                                            // The length of the player name subtracted by the length of the provided name
                                            // equals to the number of characters the player name is longer.
                                            $curDelta = strlen($row["playerName"]) - strlen($playerName);
                                            // Only replace our found row if the current row is a better match than the
                                            // already found row.
                                            if ($curDelta < $delta) {
                                                $foundRow = $row;
                                                $delta = $curDelta;
                                            }
                                            // If that number equals 0, we have a perfect match and can break, as we found
                                            // the player.
                                            if ($curDelta === 0) {
                                                break;
                                            }
                                        }
                                    }
                                    if ($foundRow === null) {
                                        $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("skull.playerNotFound", [$playerName]));
                                        return;
                                    }
                                    $item = Skull::fromData($foundRow["playerUUID"], base64_decode($foundRow["skinData"]));
                                    $done($sender, $item, $foundRow[$playerName]);
                                },
                                function (SqlError $error) use ($sender) : void {
                                }
                            );
                            return;
                        }
                        $item = Skull::fromPlayer($player);
                        $playerName = $player->getName();
                    } else {
                        $item = Skull::fromPlayer($sender);
                        $playerName = $sender->getName();
                    }
                    $done($sender, $item, $playerName);
                }
            },
            function (SqlError $error) use ($sender) : void {
                if (!$sender->isOnline()) {
                    return;
                }
                $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("skull.getLastCommandUseError", [$error->getMessage()]));
            }
        );
    }

    /**
     * Get the cooldown for the /skull command for a player by his permissions.
     * @return int | null     cooldown in seconds or NULL if no permissions
     */
    public function getCooldownOfPlayer(Player $player) : ?int {
        // With this permission, the player has no cooldown, so we return 0.
        // This would be the same as "cskull.cooldown.0".
        if ($player->hasPermission("cskull.cooldown.none")) {
            return 0;
        }
        // We are only searching for permissions that start with our prefix "cskull.cooldown.".
        // The rest is irrelevant for us.
        $permissions = array_filter(
            $player->getEffectivePermissions(),
            function (string $name) : bool {
                return str_starts_with($name, "cskull.cooldown.");
            },
            ARRAY_FILTER_USE_KEY
        );

        // We sort the array by using the ksort function.
        // This function with both flags, sorts this array, so that all of our previously filtered permissions are
        // put in such a order, where those permissions with the lowest numbers at the end, are put in front of those
        // with higher numbers (ascending order).
        ksort($permissions, SORT_FLAG_CASE | SORT_NATURAL);
        /** @var string $permissionName */
        /** @var Permission $permission */
        foreach ($permissions as $permissionName => $permission) {
            // We cut "cskull.cooldown." off from the permission name so that only the number is left.
            $cooldownString = substr($permissionName, 16);
            // If it's not numeric, we can't work with it.
            if (!is_numeric($cooldownString)) {
                continue;
            }
            $cooldown = (int) $cooldownString;
            // We can accept 0, as this would be the same as "cskull.cooldown.none", but we don't want negative
            // cooldown values.
            if ($cooldown < 0) {
                continue;
            }
            return $cooldown;
        }
        // No permission was found, so we return NULL to indicate that.
        return null;
    }
}