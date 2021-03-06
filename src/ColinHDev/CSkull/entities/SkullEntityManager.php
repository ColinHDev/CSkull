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
    /** @var array<string, array<int, \Closure>> */
    private array $spawns;
    /** @var array<string, int> */
    private array $lastTicks;

    public function __construct() {
        $this->task = new ClosureTask(
            function () : void {
                $server = Server::getInstance();
                $currentTick = $server->getTick();
                $nextTick = null;
                foreach ($this->spawns as $playerName => $closures) {
                    // It must be checked whether skull entities can be spawned to the player or despawned from it, or
                    // if the player is still on delay.
                    if (isset($this->lastTicks[$playerName])) {
                        $requestedTick = $this->lastTicks[$playerName] + $this->spawnDelay;
                        if ($requestedTick > $currentTick) {
                            $nextTick = min($requestedTick, $nextTick ?? PHP_INT_MAX);
                            continue;
                        }
                    }
                    // An entity can not be spawned to or despawned from a player that is not online.
                    $player = $server->getPlayerExact($playerName);
                    if ($player === null) {
                        unset($this->spawns[$playerName]);
                        unset($this->lastTicks[$playerName]);
                        continue;
                    }
                    // While spawning the entities the limit must not be exceeded.
                    $spawns = 0;
                    foreach ($closures as $entityID => $closure) {
                        $closure();
                        unset($this->spawns[$playerName][$entityID]);
                        $spawns++;
                        if ($spawns >= $this->maxSpawnsPerTick) {
                            break;
                        }
                    }
                    // If no skull entities need to be spawned to the player or despawned from it, those elements can be
                    // removed from the arrays, so the arrays don't grow indefinitely.
                    // TODO: If another skull entity is scheduled for a player, whose elements were just removed, that
                    //  entity would be scheduled on an earlier tick, although the player should still be on delay.
                    if (count($this->spawns[$playerName]) === 0) {
                        unset($this->spawns[$playerName]);
                        unset($this->lastTicks[$playerName]);
                        continue;
                    }
                    $this->lastTicks[$playerName] = $currentTick;
                    $nextTick = min($currentTick + $this->spawnDelay, $nextTick ?? PHP_INT_MAX);
                }
                $this->task->setHandler(null);
                if ($nextTick !== null) {
                    $this->scheduleTask($nextTick - $currentTick);
                }
            }
        );
        $config = ResourceManager::getInstance()->getConfig();
        $this->spawnDelay = max((int) $config->get("skullEntity.spawn.delay", 1), 0);
        $this->maxSpawnsPerTick = max((int) $config->get("skullEntity.spawn.maxPerTick", 2), 1);
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
        $entityID = $entity->getId();
        if (isset($this->skullEntities[$worldID][$chunkHash][$entityID])) {
            unset($this->skullEntities[$worldID][$chunkHash][$entityID]);
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
    }

    /**
     * Schedules the given entity to be spawned to the player or despawned from it.
     * @param bool $spawn Whether the entity should be spawned (true) to the player or despawned (false) from it.
     */
    public function scheduleEntitySpawn(Player $player, SkullEntity $entity, bool $spawn) : void {
        // If the entity should spawn and the player is already a viewer or if the entity should despawn and the player
        // is no viewer, we don't need to schedule that update as our goal is already achieved.
        if ($spawn xor !isset($entity->getViewers()[spl_object_id($player)])) {
            // Old spawns still need to be removed, as they could override the current goal.
            unset($this->spawns[$player->getName()][$entity->getId()]);
            return;
        }
        // The skull entity only needs to be scheduled if the delay is larger than 0.
        // If not, it can be handled directly.
        if ($this->spawnDelay > 0) {
            $this->spawns[$player->getName()][$entity->getId()] = (fn () => $entity->handleSpawn($player, $spawn));
            $this->scheduleTask(1);
            return;
        }
        $entity->handleSpawn($player, $spawn);
    }

    /**
     * @param int $delay The number of ticks to which the task is delayed.
     */
    public function scheduleTask(int $delay) : void {
        if ($this->task->getHandler() !== null) {
            return;
        }
        CSkull::getInstance()->getScheduler()->scheduleDelayedTask($this->task, $delay);
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