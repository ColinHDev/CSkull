<?php

namespace ColinHDev\CSkull\listeners;

use ColinHDev\CSkull\blocks\Skull;
use ColinHDev\CSkull\DataProvider;
use ColinHDev\CSkull\entities\SkullEntity;
use ColinHDev\CSkull\entities\SkullEntityManager;
use pocketmine\entity\Location;
use pocketmine\event\Listener;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\world\ChunkLockId;
use poggit\libasynql\SqlError;

class ChunkLoadListener implements Listener {

    public function onChunkLoad(ChunkLoadEvent $event) : void {
        $world = $event->getWorld();
        $chunkX = $event->getChunkX();
        $chunkZ = $event->getChunkZ();
        // This could happen if the event is called for a freshly populated chunk, which is still locked due to the chunk population.
        // So we can return, as we don't expect the chunk to contain any skulls then anyway.
        if ($world->isChunkLocked($chunkX, $chunkZ)) {
            return;
        }
        // We lock the chunk so that while the query is being executed, players can't break any blocks in the chunk.
        // Otherwise, this could result in players breaking skulls before their corresponding entity could be spawned,
        // so that a normal player skull would be dropped and the skull entity not spawned after the query.
        $chunkLockID = new ChunkLockId();
        $world->lockChunk($chunkX, $chunkZ, $chunkLockID);
        DataProvider::getInstance()->getSkullsByChunk(
            $world->getFolderName(),
            $chunkX,
            $chunkZ,
            function (array $rows) use ($world, $chunkX, $chunkZ, $chunkLockID) : void {
                if (!$world->isLoaded()) {
                    return;
                }
                foreach ($rows as $row) {
                    if (!$world->isInWorld($row["x"], $row["y"], $row["z"])) {
                        continue;
                    }
                    $block = $world->getBlockAt($row["x"], $row["y"], $row["z"]);
                    // We need to make sure, that the block is still valid before we spawn the entity.
                    // This could be the result of for example editing the world while the plugin wasn't enabled.
                    if (SkullEntityManager::isBlockValid($block)) {
                        // We need the block to get the respective location of the entity.
                        /** @var Skull $block */
                        $location = Location::fromObject(
                            $block->getFacingDependentPosition()->asVector3(),
                            $world,
                            $block->getEntityYaw(),
                            0.0
                        );
                        $skullEntity = new SkullEntity($location, $row["playerUUID"], $row["playerName"], $row["skinData"]);
                        $skullEntity->spawnToAll();
                        continue;
                    }
                    // If the block is not valid, we can delete that row from the database, as the skull was removed
                    // while the plugin wasn't enabled.
                    // We don't provide any callbacks, as it isn't really important whether the query succeeds or not,
                    // since we can't do much about it. In the worst case, the query fails and the row isn't deleted,
                    // so that this skull entity is queried again, when the chunk is eventually loaded again. Then just
                    // another delete query will be executed. There would still be the possibility that a player tries
                    // to place a new skull at the non-deleted position, but since we "REPLACE INTO" the database,
                    // shouldn't this be any problem.
                    DataProvider::getInstance()->deleteSkullByPosition(
                        $row["worldName"],
                        $row["x"],
                        $row["y"],
                        $row["z"]
                    );
                }
                $world->unlockChunk($chunkX, $chunkZ, $chunkLockID);
            },
            function (SqlError $error) use ($world, $chunkX, $chunkZ, $chunkLockID) : void {
                // Even if the query has failed, we need to make sure that the chunk is unlocked, so it doesn't stay
                // in its locked state for eternity.
                if ($world->isLoaded()) {
                    $world->unlockChunk($chunkX, $chunkZ, $chunkLockID);
                }
            }
        );
    }
}