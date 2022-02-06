<?php

namespace ColinHDev\CSkull\listeners;

use ColinHDev\CSkull\blocks\Skull;
use ColinHDev\CSkull\DataProvider;
use ColinHDev\CSkull\entities\SkullEntity;
use ColinHDev\CSkull\entities\SkullEntityManager;
use ColinHDev\CSkull\items\Skull as SkullItem;
use pocketmine\block\utils\SkullType;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\nbt\NoSuchTagException;
use pocketmine\nbt\UnexpectedTagTypeException;
use poggit\libasynql\SqlError;

class BlockPlaceListener implements Listener {

    /**
     * @handleCancelled false
     * @priority MONITOR
     */
    public function onBlockPlace(BlockPlaceEvent $event) : void {
        $block = $event->getBlock();
        if (!($block instanceof Skull) || $block->getSkullType() !== SkullType::PLAYER()) {
            return;
        }
        $nbt = $event->getItem()->getNamedTag();
        try {
            $playerUUID = $nbt->getString("PlayerUUID");
            $playerName = $nbt->getString("PlayerName");
            $skinData = $nbt->getByteArray("SkinData");
        } catch (UnexpectedTagTypeException | NoSuchTagException) {
            // If these exceptions are thwrown, the item does not have these tags and therefore is a normal
            // player skull instead of one with a skin assigned.
            return;
        }
        // We could also spawn the entity when the query succeeded so we would not need to despawn the entity again
        // if the query failed, but that would result in problems with getSkullEntity() and therefore getDrops(),
        // because the block could be tried to destroy while the query is still executed.
        $skullEntity = new SkullEntity($block->getFacingDependentLocation(), $playerUUID, $playerName, $skinData);
        $skullEntity->spawnToAll();
        $player = $event->getPlayer();
        $blockReplace = $event->getBlockReplaced();
        $position = $block->getPosition();
        DataProvider::getInstance()->setSkull(
            $position->world->getFolderName(),
            $position->x,
            $position->y,
            $position->z,
            $playerUUID,
            $skinData,
            null,
            static function (SqlError $error) use ($position, $player, $skullEntity, $blockReplace, $playerUUID, $playerName, $skinData) : void {
                // The query failed, so we need to undo the placement of this skull.
                // As explained above, we also need to check, if the block is still valid or also broken while the query
                // failed.
                $block = $position->world->getBlockAt($position->x, $position->y, $position->z, true, false);
                if (SkullEntityManager::isBlockValid($block)) {
                    // We need to set the block to the one we replaced with the skull.
                    $position->world->setBlock($position, $blockReplace);
                    // And give the player the skull item back, if he is still online.
                    if ($player->isOnline()) {
                        $player->getInventory()->addItem(SkullItem::fromData($playerUUID, $playerName, $skinData));
                    }
                }
                // We also need to despawn the entity, if it isn't already.
                if (!$skullEntity->isFlaggedForDespawn() && !$skullEntity->isClosed()) {
                    $skullEntity->flagForDespawn();
                }
            }
        );
    }
}