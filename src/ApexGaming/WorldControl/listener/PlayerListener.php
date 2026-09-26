<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\listener;

use ApexGaming\WorldControl\rule\Rule;
use ApexGaming\WorldControl\WorldControl;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\math\Vector3;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

/** Player rules: combat, hunger, death, chat, commands, gamemode, world entry, border, spawn. */
final class PlayerListener implements Listener{

    /** @var array<string, true> players between PlayerJoinEvent and their first rules check */
    private array $joining = [];

    public function __construct(private WorldControl $plugin){}

    private function later(int $ticks, \Closure $task) : void{
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask($task), $ticks);
    }

    // ------------------------------------------------------------ join / quit

    public function onJoin(PlayerJoinEvent $event) : void{
        $player = $event->getPlayer();
        $settings = $this->plugin->getServerSettings();
        if(!$settings->isEnabled("join-message")){
            $event->setJoinMessage("");
        }

        $name = strtolower($player->getName());
        $this->joining[$name] = true;

        $rules = $this->plugin->rulesOf($player->getWorld());
        if($settings->isEnabled("always-spawn")
            || ($rules->allows(Rule::LOCKED) && !$this->plugin->canBypass($player, "enter"))){
            $this->plugin->teleportToSpawn($player);
        }

        // a second later, so the title isn't lost while the client is still loading
        $this->later(20, function() use ($player, $name) : void{
            unset($this->joining[$name]);
            if(!$player->isConnected()) return;
            $this->plugin->getEnforcer()->applyPlayer($player);
            $this->plugin->getEnforcer()->sendWelcome($player);
        });
    }

    public function onQuit(PlayerQuitEvent $event) : void{
        if(!$this->plugin->getServerSettings()->isEnabled("quit-message")){
            $event->setQuitMessage("");
        }
        unset($this->joining[strtolower($event->getPlayer()->getName())]);
        $this->plugin->forgetPlayer($event->getPlayer());
    }

    public function onRespawn(PlayerRespawnEvent $event) : void{
        $player = $event->getPlayer();
        if($this->plugin->getServerSettings()->isEnabled("respawn-at-spawn")){
            $spawn = $this->plugin->getServerSettings()->getSpawn();
            if($spawn !== null){
                $event->setRespawnPosition($spawn);
            }
        }
        $from = $player->getWorld();
        $this->later(1, function() use ($player, $from) : void{
            if(!$player->isConnected()) return;
            $changed = $player->getWorld() !== $from;
            $this->plugin->getEnforcer()->applyPlayer($player, $changed ? $from : null);
            if($changed){
                $this->plugin->getEnforcer()->sendWelcome($player);
            }
        });
    }

    // ------------------------------------------------------------ changing worlds

    /**
     * Locked and full worlds.
     *
     * @priority HIGH
     */
    public function onTeleport(EntityTeleportEvent $event) : void{
        $player = $event->getEntity();
        $from = $event->getFrom()->getWorld();
        $to = $event->getTo()->getWorld();
        if(!$player instanceof Player || $from === $to) return;
        if($this->plugin->isTrustedTeleport($player) || $this->plugin->canBypass($player, "enter")) return;

        $rules = $this->plugin->rulesOf($to);
        $translator = $this->plugin->getTranslator();
        if($rules->allows(Rule::LOCKED)){
            $event->cancel();
            $translator->send($player, "deny.locked", ["world" => $to->getFolderName()]);
            return;
        }
        $max = $rules->int(Rule::MAX_PLAYERS);
        if($max > 0 && count($to->getPlayers()) >= $max){
            $event->cancel();
            $translator->send($player, "deny.full", ["world" => $to->getFolderName(), "max" => $max]);
        }
    }

    /**
     * Once the teleport is certain: gamemode, flight and the welcome message of the new world.
     *
     * @priority MONITOR
     */
    public function onTeleported(EntityTeleportEvent $event) : void{
        $player = $event->getEntity();
        $from = $event->getFrom()->getWorld();
        if(!$player instanceof Player || $from === $event->getTo()->getWorld()) return;
        if(isset($this->joining[strtolower($player->getName())])) return; // the join task handles it

        $this->later(1, function() use ($player, $from) : void{
            if(!$player->isConnected()) return;
            $this->plugin->getEnforcer()->applyPlayer($player, $from);
            $this->plugin->getEnforcer()->sendWelcome($player);
        });
    }

    // ------------------------------------------------------------ gamemode

    /** @priority HIGH */
    public function onGameModeChange(PlayerGameModeChangeEvent $event) : void{
        $player = $event->getPlayer();
        $forced = GameMode::fromString($this->plugin->rulesOf($player->getWorld())->string(Rule::GAMEMODE));
        if($forced !== null && $event->getNewGamemode() !== $forced && !$this->plugin->canBypass($player, "gamemode")){
            $event->cancel();
            $this->plugin->deny($player, "deny.gamemode");
        }
    }

    /**
     * A new gamemode resets flight, so give the fly rule's flight back.
     *
     * @priority MONITOR
     */
    public function onGameModeChanged(PlayerGameModeChangeEvent $event) : void{
        $player = $event->getPlayer();
        $this->later(1, fn() => $this->plugin->getEnforcer()->applyPlayer($player));
    }

    // ------------------------------------------------------------ combat & survival

    public function onDamage(EntityDamageEvent $event) : void{
        $victim = $event->getEntity();
        if(!$victim instanceof Player) return;
        $rules = $this->plugin->rulesOf($victim->getWorld());
        $cause = $event->getCause();

        if($cause === EntityDamageEvent::CAUSE_VOID && ($rules->allows(Rule::VOID_RESCUE) || !$rules->allows(Rule::DAMAGE))){
            $event->cancel();
            $this->rescueFromVoid($victim);
            return;
        }
        if($cause === EntityDamageEvent::CAUSE_SUICIDE) return; // /kill keeps working

        if(!$rules->allows(Rule::DAMAGE)){
            $event->cancel();
            return;
        }
        if($cause === EntityDamageEvent::CAUSE_FALL && !$rules->allows(Rule::FALL_DAMAGE)){
            $event->cancel();
            return;
        }
        if($event instanceof EntityDamageByEntityEvent){
            // for arrows and other projectiles this is the shooter
            $attacker = $event->getDamager();
            if($attacker instanceof Player && $attacker !== $victim && !$rules->allows(Rule::PVP)
                && !$this->plugin->canBypass($attacker, "combat")){
                $event->cancel();
                $this->plugin->deny($attacker, "deny.pvp");
            }
        }
    }

    private function rescueFromVoid(Player $player) : void{
        $world = $player->getWorld();
        $player->resetFallDistance();
        $player->setMotion(Vector3::zero());
        $this->plugin->teleport($player, $this->plugin->safeSpawn($world));
        $this->plugin->deny($player, "deny.void-rescue");
    }

    public function onExhaust(PlayerExhaustEvent $event) : void{
        $player = $event->getPlayer();
        if($player instanceof Player && !$this->plugin->rulesOf($player->getWorld())->allows(Rule::HUNGER)){
            $event->cancel();
        }
    }

    public function onDeath(PlayerDeathEvent $event) : void{
        $rules = $this->plugin->rulesOf($event->getPlayer()->getWorld());
        if($rules->allows(Rule::KEEP_INVENTORY)){
            $event->setKeepInventory(true);
        }
        if($rules->allows(Rule::KEEP_XP)){
            if(method_exists($event, "setKeepXp")){
                $event->setKeepXp(true); // PocketMine 5.12+
            }else{
                $event->setXpDropAmount(0);
            }
        }
    }

    // ------------------------------------------------------------ chat & commands

    public function onChat(PlayerChatEvent $event) : void{
        $player = $event->getPlayer();
        $rules = $this->plugin->rulesOf($player->getWorld());
        if(!$rules->allows(Rule::CHAT) && !$this->plugin->canBypass($player, "chat")){
            $event->cancel();
            $this->plugin->getTranslator()->send($player, "deny.chat");
            return;
        }
        if($rules->allows(Rule::WORLD_CHAT)){
            $world = $player->getWorld();
            $event->setRecipients(array_values(array_filter(
                $event->getRecipients(),
                fn($recipient) => !$recipient instanceof Player || $recipient->getWorld() === $world
            )));
        }
    }

    public function onCommand(CommandEvent $event) : void{
        $player = $event->getSender();
        if(!$player instanceof Player) return;
        $blocked = $this->plugin->rulesOf($player->getWorld())->list(Rule::BLOCKED_COMMANDS);
        if(count($blocked) === 0 || $this->plugin->canBypass($player, "commands")) return;

        $label = strtolower(explode(" ", trim($event->getCommand()))[0]);
        $names = [$label];
        $command = $this->plugin->getServer()->getCommandMap()->getCommand($label);
        if($command !== null){
            // our own command can never be blocked, or an admin could lock themselves out
            if($command->getName() === "worldcontrol") return;
            $names = [...$names, strtolower($command->getName()), ...array_map("strtolower", $command->getAliases())];
        }
        if(count(array_intersect($names, $blocked)) > 0){
            $event->cancel();
            $this->plugin->getTranslator()->send($player, "deny.command", ["command" => $label]);
        }
    }

    // ------------------------------------------------------------ border

    public function onMove(PlayerMoveEvent $event) : void{
        $from = $event->getFrom();
        $to = $event->getTo();
        if($from->getFloorX() === $to->getFloorX() && $from->getFloorZ() === $to->getFloorZ()) return;

        $player = $event->getPlayer();
        $world = $player->getWorld();
        $radius = $this->plugin->rulesOf($world)->int(Rule::BORDER);
        if($radius <= 0 || $this->plugin->canBypass($player, "border")) return;

        $center = $world->getSpawnLocation();
        $outside = fn($pos) => ($pos->x - $center->x) ** 2 + ($pos->z - $center->z) ** 2 > $radius ** 2;
        if(!$outside($to)) return;

        if($outside($from)){
            // already outside (teleported there, or the border shrank): back to spawn
            $this->plugin->teleport($player, $this->plugin->safeSpawn($world));
        }else{
            $event->cancel();
        }
        $this->plugin->deny($player, "deny.border", ["radius" => $radius]);
    }
}
