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
use pocketmine\world\ChunkManager;
use pocketmine\world\Position;
use poggit\libasynql\SqlError;

class Skull extends PMMPSkull {

    /**
     * @var float
     * This constant stores the value of the offset between the skull block and its hitbox when it's facing in any
     * direction except @link Facing::UP.
     * The offset equals the sixth of a pixel (1 / 16 / 6 = 1 / 96).
     */
    private const BLOCK_OFFSET = 1 / 96;

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

    /**
     * Blocks only store on which integer coordinates they are placed, but skull blocks are differently placed in their
     * block based on the direction they are facing. Therefore integer coordinates are not enough precise to describe
     * the exact position of a skull block. This method returns the actual position with float coordinates of the block.
     */
    public function getFacingDependentPosition() : Position {
        // Skull blocks have a unique behaviour when being faced in any direction, except @link Facing::UP, where the
        // block itself is not aligned with its hitbox by the sixth of a pixel (1 / 16 / 6 = 1 / 96) in the
        // direction the block is facing.
        $vector3 = match ($this->facing) {
            // Subtract 1 / 96 from the z coordinate, since the block is offset by 1 / 96 towards @link Facing::NORTH (negative z).
            Facing::NORTH => $this->position->add(0.5, 0.25, 0.75 - self::BLOCK_OFFSET),
            // Add 1 / 96 to the z coordinate, since the block is offset by 1 / 96 towards @link Facing::SOUTH (positive z).
            Facing::SOUTH => $this->position->add(0.5, 0.25, 0.25 + self::BLOCK_OFFSET),
            // Subtract 1 / 96 from the x coordinate, since the block is offset by 1 / 96 towards @link Facing::WEST (negative x).
            Facing::WEST => $this->position->add(0.75 - self::BLOCK_OFFSET, 0.25, 0.5),
            // Add 1 / 96 to the x coordinate, since the block is offset by 1 / 96 towards @link Facing::EAST (positive x).
            Facing::EAST => $this->position->add(0.25 + self::BLOCK_OFFSET, 0.25, 0.5),
            // The block's facing is @link Facing::UP and therefore the block and its hitbox aren't offset by 1 / 96.
            default => $this->position->add(0.5, 0, 0.5)
        };
        return Position::fromObject($vector3, $this->position->getWorld());
    }


    /**
     * Returns
     */
    public function getFacingDependentLocation() : Location {
        switch ($this->facing) {
            case Facing::NORTH:
                $yaw = 180.0;
                break;
            case Facing::SOUTH:
                $yaw = 0.0;
                break;
            case Facing::WEST:
                $yaw = 90.0;
                break;
            case Facing::EAST:
                $yaw = 270.0;
                break;
            default:
                // Nothing guarantees that the rotation will always be between 0 and 15 and won't accept values like
                // for example -4, which would equal a rotation of 12, like real angles work (-90° = 270°, -0.5 * π = 1.5 * π).
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
                $yaw = fmod($angle, 360);
        }
        return Location::fromObject($this->getFacingDependentPosition(), $this->position->getWorld(), $yaw, 0.0);
    }

    /**
     * This method tries to get the skull entity at the block's position and returns it or NULL if no entity was found.
     */
    public function getSkullEntity() : ?SkullEntity {
        // If the position is not valid, e.g. if this block instance is not actually a part of a world, we can safely return NULL.
        if (!$this->position->isValid()) {
            return null;
        }
        // Get the skull entity by it's expected position.
        // Theoretically we would not need to provide a high value for the maximum distance, since the entity should
        // exactly be at the position of getFacingDependentPosition() but it was never tested how reliable lower values are.
        $skullEntity = $this->position->getWorld()->getNearestEntity($this->getFacingDependentPosition(), 0.25, SkullEntity::class);
        // This check is useless, since the third parameter of World::getNearestEntity() makes sure, that only an
        // instance of SkullEntity is returned. But without this check, PhpStorm cries because the return type of
        // World::getNearestEntity() and getSkullEntity() do not match. ¯\_(ツ)_/¯
        return $skullEntity instanceof SkullEntity ? $skullEntity : null;
    }
}