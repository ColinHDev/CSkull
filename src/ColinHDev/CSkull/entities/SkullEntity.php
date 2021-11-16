<?php

namespace ColinHDev\CSkull\entities;

use ColinHDev\CSkull\blocks\Skull;
use ColinHDev\CSkull\DataProvider;
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
use poggit\libasynql\SqlError;

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

        SkullEntityManager::getInstance()->addSkullEntity($this);
    }

    protected function getInitialSizeInfo() : EntitySizeInfo {
        return new EntitySizeInfo(0.0, 0.0);
    }

    public function spawnTo(Player $player) : void {
        // Before spawning the entity to the player, we need to check whether he wants to see them or not.
        DataProvider::getInstance()->getShowSkullsByUUID(
            $player->getUniqueId()->toString(),
            function (array $rows) use ($player) : void {
                if (!$player->isOnline()) {
                    return;
                }
                $this->handleSpawn($player, ((bool) $rows[array_key_first($rows)]["showSkulls"]));
            },
            function (SqlError $error) use ($player) : void {
                // If there was an error while executing the query, we just do the default behaviour and spawn the
                // entity to the player.
                if (!$player->isOnline()) {
                    return;
                }
                $this->handleSpawn($player, true);
            }
        );
    }

    public function handleSpawn(Player $player, bool $spawn) : void {
        // We need to make sure that the entity isn't flagged for despawn or already closed, so we don't send an
        // already destroyed entity.
        if (!$this->isFlaggedForDespawn() && !$this->isClosed()) {
            if ($spawn) {
                // We don't need to check whether the player should even be able to see the entity or is too far away, as
                // that is done by Entity::spawnTo().
                parent::spawnTo($player);
            } else {
                // We don't need to check whether the entity is even spawned to the player, as that is done by
                // Entity::despawnFrom().
                $this->despawnFrom($player);
            }
        }
    }

    public function attack(EntityDamageEvent $source) : void {
        $source->cancel();
    }

    public function hasMovementUpdate() : bool {
        return false;
    }

    public function flagForDespawn() : void {
        parent::flagForDespawn();
        SkullEntityManager::getInstance()->removeSkullEntity($this);
    }

    public function onChunkChanged(int $chunkX, int $chunkZ, Chunk $chunk) : void {
        // This method is called when the chunk was changed by World::setChunk(), which could be the result of e.g. a
        // WorldEdit plugin. So we need to check, whether the entity can still exist on its position or if its
        // supporting skull block was changed during the replacing of the chunk.
        $block = $this->getBlockAtPosition();
        if (!static::isBlockValid($block)) {
            // It can be despawned as it's supporting skull block is broken.
            $this->flagForDespawn();
            // As the skull block was broken, we can also remove that row from the database.
            $position = $block->getPosition();
            DataProvider::getInstance()->deleteSkullByPosition(
                $position->world->getFolderName(),
                $position->x,
                $position->y,
                $position->z
            );
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
        $block = $this->getBlockAtPosition();
        if (!static::isBlockValid($block)) {
            // It can be despawned as it's supporting skull block is broken.
            $this->flagForDespawn();
            // As the skull block was broken, we can also remove that row from the database.
            $position = $block->getPosition();
            DataProvider::getInstance()->deleteSkullByPosition(
                $position->world->getFolderName(),
                $position->x,
                $position->y,
                $position->z
            );
        }
    }

    /**
     * Returns the block at the entity's position.
     */
    public function getBlockAtPosition() : Block {
        $position = $this->getPosition();
        return $this->getWorld()->getBlockAt(
            $position->getFloorX(),
            $position->getFloorY(),
            $position->getFloorZ()
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