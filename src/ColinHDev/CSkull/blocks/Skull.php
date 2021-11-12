<?php

namespace ColinHDev\CSkull\blocks;

use ColinHDev\CSkull\entities\SkullEntity;
use pocketmine\block\Block;
use pocketmine\block\Skull as PMMPSkull;
use pocketmine\block\utils\SkullType;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\NoSuchTagException;
use pocketmine\nbt\UnexpectedTagTypeException;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\Position;

class Skull extends PMMPSkull {

    public function getDrops(Item $item) : array {
        return [$this->asItem()];
    }

    public function asItem() : Item {
        $item = parent::asItem();
        if ($this->skullType === SkullType::PLAYER()) {
            $skullEntity = $this->getSkullEntity();
            if ($skullEntity !== null) {
                $nbt = $item->getNamedTag();
                $skin = $skullEntity->getSkin();
                $nbt->setString("PlayerUUID", $skin->getSkinId());
                $nbt->setByteArray("SkinData", $skin->getSkinData());
                $item->setNamedTag($nbt);
            }
        }
        return $item;
    }

    public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool {
        $success = parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
        if ($success) {
            $nbt = $item->getNamedTag();
            try {
                $playerUUID = $nbt->getString("PlayerUUID");
                $skinData = $nbt->getByteArray("SkinData");
            } catch (UnexpectedTagTypeException | NoSuchTagException) {
                return true;
            }
            $location = Location::fromObject(
                $this->getFacingDependentPosition()->asVector3(),
                $this->position->getWorld(),
                $this->getEntityYaw(),
                0.0
            );
            $skin = new Skin($playerUUID, $skinData, "", "geometry.skullEntity", SkullEntity::GEOMETRY);
            $skullEntity = new SkullEntity($location, $skin);
            $skullEntity->spawnToAll();
        }
        return $success;
    }

    public function onBreak(Item $item, ?Player $player = null) : bool {
        $success = parent::onBreak($item, $player);
        if ($success) {
            $skullEntity = $this->getSkullEntity();
            if ($skullEntity !== null) {
                $skullEntity->flagForDespawn();
            }
        }
        return $success;
    }

    public function getFacingDependentPosition() : Position {
        $vector3 = match ($this->facing) {
            Facing::UP => $this->position->add(0.5, 0, 0.5),
            Facing::NORTH => $this->position->add(0.5, 0.25, 0.75),
            Facing::SOUTH => $this->position->add(0.5, 0.25, 0.25),
            Facing::WEST => $this->position->add(0.75, 0.25, 0.5),
            Facing::EAST => $this->position->add(0.25, 0.25, 0.5),
            default => $this->position->asVector3()
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
                // 360° / 16 (rotation states) = 22.5°
                $angle = 22.5 * $this->rotation;
                $angle += 180;
                if ($angle >= 360) {
                    $angle -= 360;
                }
                return $angle;
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