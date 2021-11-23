<?php

namespace ColinHDev\CSkull\listeners;

use ColinHDev\CSkull\entities\SkullEntity;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\event\Listener;

class EntityEffectAddListener implements Listener {

    public function onEntityEffectAdd(EntityEffectAddEvent $event) : void {
        if ($event->getEntity() instanceof SkullEntity) {
            $event->cancel();
        }
    }
}