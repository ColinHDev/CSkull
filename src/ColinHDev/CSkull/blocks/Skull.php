<?php

namespace ColinHDev\CSkull\blocks;

use ColinHDev\CSkull\DataProvider;
use ColinHDev\CSkull\entities\SkullEntity;
use ColinHDev\CSkull\entities\SkullEntityManager;
use ColinHDev\CSkull\items\Skull as SkullItem;
use pocketmine\block\Block;
use pocketmine\block\Skull as PMMPSkull;
use pocketmine\block\utils\SkullType;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\NoSuchTagException;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\Position;
use poggit\libasynql\SqlError;

class Skull extends PMMPSkull {

    public function getDrops(Item $item) : array {
        return [$this->asItem()];
    }

    public function asItem() : Item {
        $item = parent::asItem();
        if ($this->skullType === SkullType::PLAYER()) {
            $skullEntity = $this->getSkullEntity();
            if ($skullEntity !== null) {
                $item = SkullItem::fromData(
                    $skullEntity->getPlayerUUID(),
                    $skullEntity->getPlayerName(),
                    $skullEntity->getSkin()->getSkinData()
                );
            }
        }
        return $item;
    }

    public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool {
        $success = parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
        if ($success && $this->skullType === SkullType::PLAYER()) {
            $nbt = $item->getNamedTag();
            try {
                $playerUUID = $nbt->getString("PlayerUUID");
                $playerName = $nbt->getString("PlayerName");
                $skinData = $nbt->getByteArray("SkinData");
            } catch (UnexpectedTagTypeException | NoSuchTagException) {
                return true;
            }
            $location = Location::fromObject(
                $this->getFacingDependentPosition()->asVector3(),
                $this->position->world,
                $this->getEntityYaw(),
                0.0
            );
            $skullEntity = new SkullEntity($location, $playerUUID, $playerName, $skinData);
            $skullEntity->spawnToAll();
            DataProvider::getInstance()->setSkull(
                $this->position->world->getFolderName(),
                $this->position->x,
                $this->position->y,
                $this->position->z,
                $playerUUID,
                $skinData,
                function (int $insertId, int $affectedRows) : void {
                    // We could also spawn the entity here when the query succeeded, but that would result in problems
                    // with Skull::getSkullEntity() and therefore Skull::getDrops(), because the block could be tried
                    // to destroy while the query is still executed.
                },
                function (SqlError $error) use ($player, $skullEntity, $blockReplace, $playerUUID, $playerName, $skinData) : void {
                    // The query failed, so we need to undo the placement of this skull.
                    // As explained above, we also need to check, if the block is still valid or also broken while the query
                    // failed.
                    $block = $this->position->world->getBlockAt($this->position->x, $this->position->y, $this->position->z, true, false);
                    if (SkullEntityManager::isBlockValid($block)) {
                        // We need to set the block to the one we replaced with the skull.
                        $this->position->world->setBlock($this->position, $blockReplace);
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
        return $success;
    }

    /**
     *
     */
    public function getFacingDependentPosition() : Position {
        // Skull blocks have a unique behaviour when being faced in any direction, except @link Facing::UP, where the
        // block itself is not aligned with its hitbox by the sixth of a pixel (1 / 16 / 6 = 1 / 96) in the
        // direction the block is facing.
        $vector3 = match ($this->facing) {
            // Subtract 1 / 96 from the z coordinate, since the block is offset by 1 / 96 towards @link Facing::NORTH (negative z).
            Facing::NORTH => $this->position->add(0.5, 0.25, 0.75 - (1 / 96)),
            // Add 1 / 96 to the z coordinate, since the block is offset by 1 / 96 towards @link Facing::SOUTH (positive z).
            Facing::SOUTH => $this->position->add(0.5, 0.25, 0.25 + (1 / 96)),
            // Subtract 1 / 96 from the x coordinate, since the block is offset by 1 / 96 towards @link Facing::WEST (negative x).
            Facing::WEST => $this->position->add(0.75 - (1 / 96), 0.25, 0.5),
            // Add 1 / 96 to the x coordinate, since the block is offset by 1 / 96 towards @link Facing::EAST (positive x).
            Facing::EAST => $this->position->add(0.25 + (1 / 96), 0.25, 0.5),
            // The block's facing is @link Facing::UP and therefore the block and its hitbox aren't offset by 1 / 96.
            default => $this->position->add(0.5, 0, 0.5)
        };
        return Position::fromObject($vector3, $this->position->getWorld());
    }


    public function getEntityYaw() : float {
        switch ($this->facing) {
            case Facing::NORTH:
                return 180.0;
            case Facing::SOUTH:
                return 0.0;
            case Facing::WEST:
                return 90.0;
            case Facing::EAST:
                return 270.0;
            default:
                // Nothing guarantees that the rotation will always be between 0 and 15 and won't accept values like
                // for example -4, which would equal a rotation of 12, like real angles work
                // (-90° = 270°, -0.5 * π = 1.5 * π).
                // To encounter that, we first use modulo 16 on the actual rotation, which will always result in a
                // value between -16 and 15, or -16 and -1 and 0 and 15, to be more precise.
                // Since we don't want the negative values, we add 16 and use again modulo 16, in case the values were
                // already positive and are now again above 15. The modulo does not affect our previously negative
                // values, since they are now already in the range between 0 and 15.
                $baseRotation = (($this->rotation % 16) + 16) % 16;
                // First, we multiply by 22.5°, since we have 16 possible rotations (360° / 16 = 22.5°), to get the angle.
                // But that only gives us the angle in the opposite direction, so we add 180° to get the correct angle.
                $angle = ($baseRotation * 22.5) + 180;
                // We want an angle between 0° and 360° (360° excluded) but by adding 180° we could have gone over that
                // range, so we use modulo of 360, to get the angle within the boundaries of that range.
                // We use the fmod() function instead of the modulo operator like before, since the modulo operator only
                // works with integers, which is fine for the rotations that are integer values, but not for an angle,
                // which is a float value.
                return fmod($angle, 360);
        }
    }

    public function getSkullEntity() : ?SkullEntity {
        if (!$this->position->isValid()) {
            return null;
        }
        $skullEntity = $this->position->getWorld()->getNearestEntity($this->getFacingDependentPosition(), 0.25, SkullEntity::class);
        if ($skullEntity instanceof SkullEntity) {
            return $skullEntity;
        }
        return null;
    }
}