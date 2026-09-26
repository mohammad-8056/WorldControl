<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\listener;

use ApexGaming\WorldControl\WorldControl;
use pocketmine\event\Listener;
use pocketmine\event\world\WorldLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;

final class WorldListener implements Listener{

    public function __construct(private WorldControl $plugin){}

    public function onLoad(WorldLoadEvent $event) : void{
        $this->plugin->getEnforcer()->applyWorld($event->getWorld());
    }

    /** @priority MONITOR */
    public function onUnload(WorldUnloadEvent $event) : void{
        $this->plugin->getEnforcer()->forgetWorld($event->getWorld());
    }
}
