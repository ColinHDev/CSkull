<?php

namespace ColinHDev\CSkull;

use ColinHDev\CSkull\blocks\Skull as SkullBlock;
use ColinHDev\CSkull\entities\SkullEntity;
use ColinHDev\CSkull\items\Skull as SkullItem;
use pocketmine\block\BlockFactory;
use pocketmine\block\utils\SkullType;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\event\Listener;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;

class CSkull extends PluginBase implements Listener {

    private static CSkull $instance;

    public static function getInstance() : CSkull {
        return self::$instance;
    }

    public function onLoad() : void {
        self::$instance = $this;

        $oldSkullBlock = VanillaBlocks::MOB_HEAD();
        BlockFactory::getInstance()->register(
            new SkullBlock(
                $oldSkullBlock->getIdInfo(),
                $oldSkullBlock->getName(),
                $oldSkullBlock->getBreakInfo()
            ),
            true
        );

        foreach(SkullType::getAll() as $skullType){
            ItemFactory::getInstance()->register(
                new SkullItem(
                    new ItemIdentifier(ItemIds::SKULL, $skullType->getMagicNumber()),
                    $skullType->getDisplayName(),
                    $skullType
                ),
                true
            );
        }

        EntityFactory::getInstance()->register(
            SkullEntity::class,
            function(World $world, CompoundTag $nbt) : SkullEntity {
                return new SkullEntity(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
            },
            ["SkullEntity"]
        );
    }

    public function onEnable() : void {

    }
}