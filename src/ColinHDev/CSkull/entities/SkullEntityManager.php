<?php

namespace ColinHDev\CSkull\entities;

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
        $worldID = $position->getWorld()->getId();
        if (!isset($this->skullEntities[$worldID])) {
            $this->skullEntities[$worldID] = [];
        }
        $chunkHash = World::chunkHash($position->getFloorX() >> Chunk::COORD_BIT_SIZE, $position->getFloorZ() >> Chunk::COORD_BIT_SIZE);
        if (!isset($this->skullEntities[$worldID][$chunkHash])) {
            $this->skullEntities[$worldID][$chunkHash] = [];
        }
        $this->skullEntities[$worldID][$chunkHash][$entity->getId()] = $entity;
    }

    public function removeSkullEntity(SkullEntity $entity) : void {
        $position = $entity->getPosition();
        $worldID = $position->getWorld()->getId();
        $chunkHash = World::chunkHash($position->getFloorX() >> Chunk::COORD_BIT_SIZE, $position->getFloorZ() >> Chunk::COORD_BIT_SIZE);
        $entityID = $entity->getId();
        unset($this->skullEntities[$worldID][$chunkHash][$entityID]);
        if (count($this->skullEntities[$worldID][$chunkHash]) === 0) {
            unset($this->skullEntities[$worldID][$chunkHash]);
        }
        if (count($this->skullEntities[$worldID]) === 0) {
            unset($this->skullEntities[$worldID]);
        }
    }
}