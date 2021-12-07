<?php

namespace ColinHDev\CSkull\listeners;

use ColinHDev\CSkull\DataProvider;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;

class PlayerLoginListener implements Listener {

    public function onPlayerLogin(PlayerLoginEvent $event) : void {
        $player = $event->getPlayer();
        DataProvider::getInstance()->setSkinData(
            $player->getUniqueId()->getBytes(),
            $player->getName(),
            $player->getSkin()->getSkinData()
        );
    }
}