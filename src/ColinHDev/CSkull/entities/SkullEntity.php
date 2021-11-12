<?php

namespace ColinHDev\CSkull\entities;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

class SkullEntity extends Human {

    public const GEOMETRY =
        '{
	        "geometry.skullEntity": {
		        "texturewidth": 64,
		        "textureheight": 64,
		        "visible_bounds_width": 2,
		        "visible_bounds_height": 1,
		        "visible_bounds_offset": [0, 0.5, 0],
		        "bones": [{
				                "name": "head",
				                "pivot": [0, 0, 0],
				                "cubes": [
					                {"origin": [-4, 0.5, -4], "size": [8, 8, 8], "uv": [0, 0], "inflate": 0.75},
					                {"origin": [-4, 0.5, -4], "size": [8, 8, 8], "uv": [32, 0], "inflate": 1.25}
				                ]
			    }]
	        }
        }';

    public function initEntity(CompoundTag $nbt) : void {
        parent::initEntity($nbt);
        $this->setMaxHealth(1);

        $this->setCanSaveWithChunk(false);
        $this->setImmobile(true);
        $this->setHasGravity(false);

        $this->setNameTagVisible(false);
        $this->setNameTagAlwaysVisible(false);
    }

    protected function getInitialSizeInfo() : EntitySizeInfo {
        return new EntitySizeInfo(0.0, 0.0);
    }

    public function spawnTo(Player $player) : void {
        parent::spawnTo($player);
    }

    public function attack(EntityDamageEvent $source) : void {
        $source->cancel();
    }
}