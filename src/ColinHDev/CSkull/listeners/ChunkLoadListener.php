<?php

namespace ColinHDev\CSkull\listeners;

use ColinHDev\CSkull\blocks\Skull;
use ColinHDev\CSkull\DataProvider;
use ColinHDev\CSkull\entities\SkullEntity;
use ColinHDev\CSkull\entities\SkullEntityManager;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
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
        $chunkLockID = new ChunkLockId();
        $world->lockChunk($chunkX, $chunkZ, $chunkLockID);
        DataProvider::getInstance()->getSkullsByChunk(
            $world->getFolderName(),
            $chunkX,
            $chunkZ,
            function (array $rows) use ($world, $chunkX, $chunkZ, $chunkLockID) : void {
                if ($world->isLoaded()) {
                    foreach ($rows as $row) {
                        if ($world->isInWorld($row["x"], $row["y"], $row["z"])) {
                            $block = $world->getBlockAt($row["x"], $row["y"], $row["z"]);
                            if (SkullEntityManager::isBlockValid($block)) {
                                /** @var Skull $block */
                                $location = Location::fromObject(
                                    $block->getFacingDependentPosition()->asVector3(),
                                    $world,
                                    $block->getEntityYaw(),
                                    0.0
                                );
                                $skin = new Skin($row["playerUUID"], $row["skinData"], "", "geometry.skullEntity", SkullEntity::GEOMETRY);
                                $skullEntity = new SkullEntity($location, $skin);
                                $skullEntity->spawnToAll();
                                continue;
                            }
                            DataProvider::getInstance()->deleteSkullByPosition(
                                $row["worldName"],
                                $row["x"],
                                $row["y"],
                                $row["z"]
                            );
                        }
                    }
                    $world->unlockChunk($chunkX, $chunkZ, $chunkLockID);
                }
            },
            function (SqlError $error) use ($world, $chunkX, $chunkZ, $chunkLockID) : void {
                if ($world->isLoaded()) {
                    $world->unlockChunk($chunkX, $chunkZ, $chunkLockID);
                }
            }
        );
    }
}