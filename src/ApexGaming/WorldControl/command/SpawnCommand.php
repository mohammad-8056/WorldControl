<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\command;

use ApexGaming\WorldControl\WorldControl;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;

/** /spawn — back to the server spawn. Can be switched off in the server settings. */
final class SpawnCommand extends Command implements PluginOwned{
    use PluginOwnedTrait;

    /** @param list<string> $aliases from config.yml */
    public function __construct(private WorldControl $plugin, array $aliases){
        parent::__construct("spawn", "Teleport to the server spawn", "/spawn", $aliases);
        $this->setPermission("worldcontrol.spawn");
        $this->owningPlugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
        $translator = $this->plugin->getTranslator();
        if(!$sender instanceof Player){
            $translator->send($sender, "general.players-only");
            return true;
        }
        if(!$this->plugin->getServerSettings()->isEnabled("spawn-command")){
            $translator->send($sender, "spawn.disabled");
            return true;
        }
        if($this->plugin->teleportToSpawn($sender)){
            $translator->send($sender, "spawn.teleported");
        }
        return true;
    }
}
