<?php

namespace ColinHDev\CSkull\items;

use ColinHDev\CSkull\blocks\Skull as SkullBlock;
use ColinHDev\CSkull\ResourceManager;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\utils\SkullType;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\Skull as PMMPSkull;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class Skull extends PMMPSkull {

    /**
     * We need to overwrite that method, since @link PMMPSkull::getBlock() doesn't use the BlockFactory, but
     * @link VanillaBlocks::MOB_HEAD() to get an instance of the skull block. But since VanillaBlocks returns an
     * instance of PocketMine-MP's and not our skull block, we need to overwrite this method.
     */
    public function getBlock(?int $clickedFace = null) : Block {
        /** @var SkullBlock $block */
        $block = BlockFactory::getInstance()->get(BlockLegacyIds::SKULL_BLOCK, 0);
        return $block->setSkullType($this->getSkullType());
    }

    /**
     * Get the skull item of the provided player.
     */
    public static function fromPlayer(Player $player) : self {
        return static::fromData($player->getUniqueId()->getBytes(), $player->getName(), $player->getSkin()->getSkinData());
    }

    /**
     * Get the skull item of the provided player data.
     */
    public static function fromData(string $playerUUID, string $playerName, string $skinData) : self {
        // We get the skull item from the Itemfactory because @link VanillaItems::PLAYER_HEAD() would just return an
        // instance of PocketMine-MP's and not our skull item.
        /** @var self $item */
        $item = ItemFactory::getInstance()->get(ItemIds::SKULL, SkullType::PLAYER()->getMagicNumber());
        // We get the item's nbt data to store the player's UUID, name and skin data in it.
        // The nbt data should be empty anyway, but just to be sure, we fetch it from the item instead of
        // creating a new one.
        $nbt = $item->getNamedTag();
        $nbt->setString("PlayerUUID", $playerUUID);
        $nbt->setString("PlayerName", $playerName);
        $nbt->setByteArray("SkinData", $skinData);
        $item->setNamedTag($nbt);
        // We get the item name with the player name as a parameter from the language files.
        $itemName = ResourceManager::getInstance()->translateString("skullItem.name", [$playerName]);
        // In case someone doesn't want the skulls to have a custom name, we don't set it if the name is an empty string.
        if ($itemName !== "") {
            $item->setCustomName($itemName);
        }
        // We get the item lore with the player name as a parameter from the language files.
        $itemLore = ResourceManager::getInstance()->translateString("skullItem.lore", [$playerName]);
        // In case someone doesn't want the skulls to have a custom lore, we don't set it if the lore is an empty string.
        if ($itemLore !== "") {
            // Since line breaks in the item lore are indicated through different array values (one line per value),
            // we split the string between "new line characters" ("\n").
            $item->setLore(explode(TextFormat::EOL, $itemLore));
        }
        return $item;
    }
}