<?php

namespace ColinHDev\CSkull\entities;

use ColinHDev\CSkull\blocks\Skull;
use pocketmine\block\Block;
use pocketmine\block\utils\SkullType;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

class SkullEntityManager {
    use SingletonTrait;

    /** @var array<int, array<int, array<int, SkullEntity>>> */
    private array $skullEntities = [];

    public function getSkullEntitiesByChunk(World $world, int $chunkHash) : array {
        $worldID = $world->getId();
        if (!isset($this->skullEntities[$worldID])) {
            return [];
        }
        if (!isset($this->skullEntities[$worldID][$chunkHash])) {
            return [];
        }
        return $this->skullEntities[$worldID][$chunkHash];
    }

    public function addSkullEntity(SkullEntity $entity) : void {
        $position = $entity->getPosition();
        $world = $position->getWorld();
        $worldID = $world->getId();
        if (!isset($this->skullEntities[$worldID])) {
            $this->skullEntities[$worldID] = [];
        }
        $chunkX = $position->getFloorX() >> Chunk::COORD_BIT_SIZE;
        $chunkZ = $position->getFloorZ() >> Chunk::COORD_BIT_SIZE;
        $chunkHash = World::chunkHash($chunkX, $chunkZ);
        if (!isset($this->skullEntities[$worldID][$chunkHash])) {
            $this->skullEntities[$worldID][$chunkHash] = [];
        }
        $this->skullEntities[$worldID][$chunkHash][$entity->getId()] = $entity;
        $world->registerChunkListener($entity, $chunkX, $chunkZ);
    }

    public function removeSkullEntity(SkullEntity $entity) : void {
        $position = $entity->getPosition();
        $world = $position->getWorld();
        $worldID = $world->getId();
        $chunkX = $position->getFloorX() >> Chunk::COORD_BIT_SIZE;
        $chunkZ = $position->getFloorZ() >> Chunk::COORD_BIT_SIZE;
        $chunkHash = World::chunkHash($chunkX, $chunkZ);
        unset($this->skullEntities[$worldID][$chunkHash][$entity->getId()]);
        // We need to unregister this entity from its chunk as ChunkListener when it's removed and therefore
        // flagged for despawn.
        $world->unregisterChunkListener($entity, $chunkX, $chunkZ);
        if (count($this->skullEntities[$worldID][$chunkHash]) === 0) {
            unset($this->skullEntities[$worldID][$chunkHash]);
        }
        if (count($this->skullEntities[$worldID]) === 0) {
            unset($this->skullEntities[$worldID]);
        }
    }

    /**
     * Checks whether a skull entity can exist on the given block.
     */
    public static function isBlockValid(Block $block) : bool {
        // Skull entities can only exist on skull blocks of the player skull type.
        if ($block instanceof Skull) {
            if ($block->getSkullType() === SkullType::PLAYER()) {
                return true;
            }
        }
        return false;
    }
}