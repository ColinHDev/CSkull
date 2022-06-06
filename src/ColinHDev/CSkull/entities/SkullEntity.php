<?php

namespace ColinHDev\CSkull\entities;

use ColinHDev\CSkull\DataProvider;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\player\Player;
use pocketmine\world\ChunkListener;
use pocketmine\world\format\Chunk;
use poggit\libasynql\SqlError;

class SkullEntity extends Human implements ChunkListener {

    /**
     * The geometry of each skull entity.
     * It is slightly bigger than the normal player skull geometry (this can be seen at both "inflate" values
     * (which would be 0 for the first and only 0.5 for the second)). Both head layers are in comparison to the
     * vanilla values, expanded by @link SkullEntity::INFLATE_GEOMETRY. This was done because otherwise, the skull block under the entity would be
     * partly visible, especially in those places where there is no second head layer. So we increase the size of the
     * geometry so that the skull block below is never visible and will always be hidden underneath the entity.
     * By setting @link SkullEntity::INFLATE_GEOMETRY to 0.3 and moving the geometry upwards with the y origin value of
     * 0.125, we get the perfect result, where the entity's size is only slightly bigger than the hitbox of the skull block.
     */
    private const GEOMETRY =
        '{
	        "geometry.skullEntity": {
		        "texturewidth": 64,
		        "textureheight": 64,
		        "bones": [{
				    "name": "head",
				    "pivot": [0, 0, 0],
				    "cubes": [
					    {
					        "origin": [-4, 0.125, -4], 
					        "size": [8, 8, 8], 
					        "uv": [0, 0], 
					        "inflate": ' . self::INFLATE_GEOMETRY . '
					    },
					    {
					        "origin": [-4, 0.125, -4],
					        "size": [8, 8, 8], 
					        "uv": [32, 0], 
					        "inflate": ' . (0.5 + self::INFLATE_GEOMETRY) . '
					    }
				    ]
			    }]
	        }
        }';
    private const INFLATE_GEOMETRY = 0.3;

    private string $playerUUID;
    private string $playerName;

    public function __construct(Location $location, string $playerUUID, string $playerName, string $skinData) {
        $this->playerUUID = $playerUUID;
        $this->playerName = $playerName;
        parent::__construct(
            $location,
            new Skin($playerUUID, $skinData, "", "geometry.skullEntity", self::GEOMETRY)
        );
        SkullEntityManager::getInstance()->addSkullEntity($this);
    }

    public function initEntity(CompoundTag $nbt) : void {
        parent::initEntity($nbt);

        // We don't want the entity to be saved to disk so that we can respawn it whenever its chunk is loaded.
        // We do that because if the server would start without the plugin being enabled, our entities wouldn't be
        // registered. If chunks, that have our entities inside of them are loaded then, these entities would be
        // deleted and for each, a warning in the console would be displayed, which could spam the console if it's a
        // chunk with many skulls. So our way with creating the entities when the chunk loads, neither spams the
        // console if you don't want to use the plugin anymore nor does it delete your existing skulls if the plugin
        // couldn't be loaded.
        $this->setCanSaveWithChunk(false);

        // The entity is meant to give the illusion of implementing player skulls, the way Minecraft Java Edition
        // has them, on the server. So we don't want the entity to give any sign of being something apart from a block,
        // which includes not showing nametags.
        $this->setNameTagVisible(false);
        $this->setNameTagAlwaysVisible(false);

        // Disables that experience orbs are attracted to skull entities.
        $this->xpManager->setCanAttractXpOrbs(false);
    }

    protected function getInitialSizeInfo() : EntitySizeInfo {
        // We set the height and width to 0.0 so that the entity has no hitbox which could make it difficult for
        // players to break the underlying skull block.
        // This would be extra worse since the entity's geometry is slightly bigger than the skull block itself.
        return new EntitySizeInfo(0.0, 0.0);
    }

    public function getPlayerUUID() : string {
        return $this->playerUUID;
    }

    public function getPlayerName() : string {
        return $this->playerName;
    }

    public function spawnTo(Player $player) : void {
        // Before spawning the entity to the player, we need to check whether he wants to see them or not.
        DataProvider::getInstance()->getShowSkullsByUUID(
            $player->getUniqueId()->getBytes(),
            function (array $rows) use ($player) : void {
                if (!$player->isOnline()) {
                    return;
                }
                SkullEntityManager::getInstance()->scheduleEntitySpawn(
                    $player,
                    $this,
                    ((bool) $rows[array_key_first($rows)]["showSkulls"])
                );
            },
            function (SqlError $error) use ($player) : void {
                // If there was an error while executing the query, we just do the default behaviour and spawn the
                // entity to the player.
                if (!$player->isOnline()) {
                    return;
                }
                SkullEntityManager::getInstance()->scheduleEntitySpawn($player, $this, true);
            }
        );
    }

    /**
     * This method is used especially for spawning / despawning entities after a query was executed, and has built-in
     * checks to ensure that the entity for example wasn't closed during the query.
     * Although this could be implemented without an extra method, it reduces code duplication in various places.
     * @internal
     * @param bool $spawn Whether the entity should be spawned (true) to the player or despawned (false) from it.
     */
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

    /**
     * By overwriting {@link Entity::onUpdate()}, we can stop unnecessary updates on these entities, which just degrade
     * performance while being useless in this case, since these entities won't update or change in any way.
     * This also has the side effect of disabling the entire movement including gravity, collision with other entities
     * and the movement through water or explosions. So it makes these entities completely immobile.
     */
    public function onUpdate(int $currentTick) : bool {
        // We return false so that no further update for this entity is scheduled.
        return false;
    }

    /**
     * We need to overwrite this method to set the return to false because otherwise some blocks like fences
     * couldn't be placed when trying to click under the entity.
     */
    public function canBeCollidedWith() : bool {
        return false;
    }

    public function flagForDespawn() : void {
        if (!$this->isFlaggedForDespawn() && !$this->isClosed()) {
            parent::flagForDespawn();
            // Calling this in Entity::flagForDespawn() instead of Entity::close() (is final anyway) could normally result
            // in a problem, where closed entities would still be listed inside the SkullEntityManager, which would need to
            // be manually filtered out, since when a world unloads, it only closes its entities without calling this method.
            // But since all our skull entities are registered as chunk listeners for their chunks,
            // ChunkListener::onChunkUnloaded() is called before Entity::close(), so we won't have any problems with
            // closed entities in the SkullEntityManager.
            SkullEntityManager::getInstance()->removeSkullEntity($this);
        }
    }

    protected function syncNetworkData(EntityMetadataCollection $properties) : void {
        parent::syncNetworkData($properties);
        // We need to overwrite that flag since PocketMine-MP hardcoded this value to true.
        // The problem is that this resulted in a visual (client-side) glitch where the entity was a quarter of a
        // block away from its supposed position when placed on fences.
        $properties->setGenericFlag(EntityMetadataFlags::HAS_COLLISION, false);
    }

    public function onChunkChanged(int $chunkX, int $chunkZ, Chunk $chunk) : void {
        // This method is called when the chunk was changed by World::setChunk(), which could be the result of e.g. a
        // WorldEdit plugin. So we need to check, whether the entity can still exist on its position or if its
        // supporting skull block was changed during the replacing of the chunk.
        $block = $this->getBlockAtPosition();
        if (!SkullEntityManager::isBlockValid($block)) {
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
        $blockAtPosition = $this->getBlockAtPosition();
        if (!SkullEntityManager::isBlockValid($blockAtPosition)) {
            // It can be despawned as it's supporting skull block is broken.
            $this->flagForDespawn();
            // As the skull block was broken, we can also remove that row from the database.
            $position = $blockAtPosition->getPosition();
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
}