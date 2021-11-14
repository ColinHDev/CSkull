<?php

namespace ColinHDev\CSkull\entities;

use ColinHDev\CSkull\blocks\Skull;
use pocketmine\block\Block;
use pocketmine\block\utils\SkullType;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\ChunkListener;
use pocketmine\world\format\Chunk;

class SkullEntity extends Human implements ChunkListener {

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

        $position = $this->getPosition();
        $this->getWorld()->registerChunkListener(
            $this,
            ((int) floor($position->x)) >> Chunk::COORD_BIT_SIZE,
            ((int) floor($position->z)) >> Chunk::COORD_BIT_SIZE
        );
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

    public function hasMovementUpdate() : bool {
        return false;
    }

    public function flagForDespawn() : void {
        parent::flagForDespawn();
        // We need to unregister this entity from its chunk as ChunkListener when it's flagged for despawn, e.g.
        // when its supporting skull block is broken.
        $position = $this->getPosition();
        $this->getWorld()->unregisterChunkListener(
            $this,
            ((int) floor($position->x)) >> Chunk::COORD_BIT_SIZE,
            ((int) floor($position->z)) >> Chunk::COORD_BIT_SIZE
        );
    }

    public function onChunkChanged(int $chunkX, int $chunkZ, Chunk $chunk) : void {
        // This method is called when the chunk was changed by World::setChunk(), which could be the result of e.g. a
        // WorldEdit plugin. So we need to check, whether the entity can still exist on its position or if its
        // supporting skull block was changed during the replacing of the chunk.
        if (!static::isBlockValid($this->getBlockAtPosition())) {
            // It can be despawned as it's supporting skull block is broken.
            $this->flagForDespawn();
        }
    }

    public function onChunkLoaded(int $chunkX, int $chunkZ, Chunk $chunk) : void {
        // We can ignore this although ChunkLoadEvent is called before this method, which could result in skull entities
        // being already registered as ChunkListener. But apart from that, we don't expect this method to be called,
        // since skull entities aren't saved to disk, so we don't need to perform any actions on this.
    }

    public function onChunkUnloaded(int $chunkX, int $chunkZ, Chunk $chunk) : void {
        // As this entity is only registered as ChunkListener for its own chunk, we don't need to check which chunk is unloaded.
        $this->getWorld()->unregisterChunkListener($this, $chunkX, $chunkZ);
    }

    public function onChunkPopulated(int $chunkX, int $chunkZ, Chunk $chunk) : void {
        // We can ignore this because player skulls don't exist in ungenerated terrain and therefore these entities
        // shouldn't be registered as ChunkListener for these types of chunks.
    }

    public function onBlockChanged(Vector3 $block) : void {
        // This method is called when a block is changed with World::setBlockAt(), e.g. when a block is broken by an
        // explosion. That's why we need to check if this entity can still exist on its position.
        if (!static::isBlockValid($this->getBlockAtPosition())) {
            // It can be despawned as it's supporting skull block is broken.
            $this->flagForDespawn();
        }
    }

    /**
     * Returns the block at the entity's position.
     */
    public function getBlockAtPosition() : Block {
        $position = $this->getPosition();
        return $this->getWorld()->getBlockAt(
            (int) floor($position->x),
            (int) floor($position->y),
            (int) floor($position->z)
        );
    }

    /**
     * Checks whether the skull entity can exist on a block.
     */
    public static function isBlockValid(Block $block) : bool {
        // It can only exist if it's on a skull block of the player skull type.
        if ($block instanceof Skull) {
            if ($block->getSkullType() === SkullType::PLAYER()) {
                return true;
            }
        }
        return false;
    }
}