<?php

namespace ColinHDev\CSkull\entities;

use ColinHDev\CSkull\blocks\Skull;
use ColinHDev\CSkull\CSkull;
use ColinHDev\CSkull\ResourceManager;
use pocketmine\block\Block;
use pocketmine\block\utils\SkullType;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

class SkullEntityManager {
    use SingletonTrait;

    /** @var array<int, array<int, array<int, SkullEntity>>> */
    private array $skullEntities = [];

    private ClosureTask $task;
    private int $spawnDelay;
    private int $maxSpawnsPerTick;
    /** @var array<int, array<string, array<int, \Closure>>> */
    private array $spawnsPerTick = [];

    public function __construct() {
        $this->task = new ClosureTask(
            function () : void {
                $server = Server::getInstance();
                $currentTick = $server->getTick();
                if (isset($this->spawnsPerTick[$currentTick])) {
                    foreach ($this->spawnsPerTick[$currentTick] as $playerName => $closures) {
                        $player = $server->getPlayerExact($playerName);
                        if ($player === null) {
                            continue;
                        }
                        foreach ($closures as $closure) {
                            $closure();
                        }
                    }
                    unset($this->spawnsPerTick[$currentTick]);
                }
                $this->task->setHandler(null);
                $this->scheduleTask();
            }
        );
        $config = ResourceManager::getInstance()->getConfig();
        $this->spawnDelay = max($config->get("skullEntity.spawn.delay", 1), 1);
        $this->maxSpawnsPerTick = max($config->get("skullEntity.spawn.maxPerTick", 1), 1);
    }

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
     * Schedules the given entity to be spawned to the player or despawned from it.
     * @param bool $spawn Whether the entity should be spawned (true) to the player or despawned (false) from it.
     */
    public function scheduleEntitySpawn(Player $player, SkullEntity $entity, bool $spawn) : void {
        $possibleTick = Server::getInstance()->getTick() + 1;
        $playerName = $player->getName();
        foreach ($this->spawnsPerTick as $tick => $players) {
            if ($possibleTick > $tick) {
                continue;
            }
            if (!isset($players[$playerName])) {
                continue;
            }
            if (count($players[$playerName]) >= $this->maxSpawnsPerTick) {
                $possibleTick = $tick + $this->spawnDelay;
            } else {
                $possibleTick = $tick;
            }
        }
        $this->spawnsPerTick[$possibleTick][$playerName][$entity->getId()] = (fn () => $entity->handleSpawn($player, $spawn));
        $this->scheduleTask();
    }

    public function scheduleTask() : void {
        if ($this->task->getHandler() !== null) {
            return;
        }
        $ticks = array_keys($this->spawnsPerTick);
        if (count($ticks) === 0) {
            return;
        }
        CSkull::getInstance()->getScheduler()->scheduleDelayedTask(
            $this->task,
            (min($ticks) - Server::getInstance()->getTick())
        );
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