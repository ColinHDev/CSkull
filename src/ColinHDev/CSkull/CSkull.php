<?php

namespace ColinHDev\CSkull;

use ColinHDev\CSkull\blocks\Skull as SkullBlock;
use ColinHDev\CSkull\commands\ShowSkullsCommand;
use ColinHDev\CSkull\commands\SkullCommand;
use ColinHDev\CSkull\items\Skull as SkullItem;
use ColinHDev\CSkull\listeners\BlockPlaceListener;
use ColinHDev\CSkull\listeners\ChunkLoadListener;
use ColinHDev\CSkull\listeners\EntityEffectAddListener;
use ColinHDev\CSkull\listeners\PlayerLoginListener;
use pocketmine\block\BlockFactory;
use pocketmine\block\utils\SkullType;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\Listener;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemIds;
use pocketmine\plugin\PluginBase;

class CSkull extends PluginBase implements Listener {

    private static CSkull $instance;

    public static function getInstance() : CSkull {
        return self::$instance;
    }

    public function onEnable() : void {
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

        foreach (SkullType::getAll() as $skullType) {
            ItemFactory::getInstance()->register(
                new SkullItem(
                    new ItemIdentifier(ItemIds::SKULL, $skullType->getMagicNumber()),
                    $skullType->getDisplayName(),
                    $skullType
                ),
                true
            );
        }

        ResourceManager::getInstance();
        DataProvider::getInstance();

        $this->getServer()->getPluginManager()->registerEvents(new BlockPlaceListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new ChunkLoadListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new EntityEffectAddListener(), $this);
        $this->getServer()->getPluginManager()->registerEvents(new PlayerLoginListener(), $this);

        $this->getServer()->getCommandMap()->register("CSkull", new ShowSkullsCommand());
        $this->getServer()->getCommandMap()->register("CSkull", new SkullCommand());
    }

    public function onDisable() : void {
        DataProvider::getInstance()->close();
    }
}