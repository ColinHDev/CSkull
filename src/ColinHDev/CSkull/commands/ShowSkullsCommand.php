<?php

namespace ColinHDev\CSkull\commands;

use ColinHDev\CSkull\DataProvider;
use ColinHDev\CSkull\entities\SkullEntityManager;
use ColinHDev\CSkull\ResourceManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use poggit\libasynql\SqlError;

class ShowSkullsCommand extends Command {

    /** @var string[] */
    private const TRUE_VALUES = ["1", "y", "yes", "allow", "true"];
    /** @var string[] */
    private const FALSE_VALUES = ["0", "no", "deny", "disallow", "false"];

    public function __construct() {
        parent::__construct(
            ResourceManager::getInstance()->translateString("showskulls.name"),
            ResourceManager::getInstance()->translateString("showskulls.description"),
            ResourceManager::getInstance()->translateString("showskulls.usage"),
            json_decode(ResourceManager::getInstance()->translateString("showskulls.alias"), true)
        );
        $this->setPermission("cskull.command.showskulls");
        $this->setPermissionMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("showskulls.permissionMessage"));
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : void {
        if (!$this->testPermission($sender)) {
            return;
        }
        if (!$sender instanceof Player) {
            $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("showskulls.notOnline"));
            return;
        }

        if (!isset($args[0])) {
            DataProvider::getInstance()->getShowSkullsByUUID(
                $sender->getUniqueId()->getBytes(),
                function (array $rows) use ($sender) : void {
                    if (!$sender->isOnline()) {
                        return;
                    }
                    $showSkulls = (bool) $rows[array_key_first($rows)]["showSkulls"];
                    if ($showSkulls) {
                        $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("showskulls.isEnabled"));
                    } else {
                        $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("showskulls.isDisabled"));
                    }
                },
                function (SqlError $error) use ($sender) : void {
                    if (!$sender->isOnline()) {
                        return;
                    }
                    $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("showskulls.getShowSkullsError", [$error->getMessage()]));
                }
            );

        } else {
            $args[0] = strtolower($args[0]);
            if (array_search($args[0], self::TRUE_VALUES, true) !== false) {
                $showSkulls = true;
            } else if (array_search($args[0], self::FALSE_VALUES, true) !== false) {
                $showSkulls = false;
            } else {
                $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . $this->getUsage());
                return;
            }
            DataProvider::getInstance()->setShowSkulls(
                $sender->getUniqueId()->getBytes(),
                $showSkulls,
                function (int $affectedRows) use ($sender, $showSkulls) : void {
                    if (!$sender->isOnline()) {
                        return;
                    }
                    $world = $sender->getWorld();
                    foreach ($sender->getUsedChunks() as $chunkHash => $chunkStatus) {
                        foreach (SkullEntityManager::getInstance()->getSkullEntitiesByChunk($world, $chunkHash) as $entity) {
                            SkullEntityManager::getInstance()->scheduleEntitySpawn($sender, $entity, $showSkulls);
                        }
                    }
                    if ($showSkulls) {
                        $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("showskulls.enabled"));
                    } else {
                        $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("showskulls.disabled"));
                    }
                },
                function (SqlError $error) use ($sender) : void {
                    if (!$sender->isOnline()) {
                        return;
                    }
                    $sender->sendMessage(ResourceManager::getInstance()->getPrefix() . ResourceManager::getInstance()->translateString("showskulls.setShowSkullsError", [$error->getMessage()]));
                }
            );
        }
    }
}