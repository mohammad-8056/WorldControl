<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\world;

use ApexGaming\WorldControl\rule\Rule;
use ApexGaming\WorldControl\WorldControl;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\World;

/**
 * Applies the rules that are a state rather than an event check: a world's
 * locked time and difficulty, and a player's gamemode, flight, food and the
 * welcome message when they arrive in a world.
 *
 * Whatever this class changes it also remembers, so turning a rule back off
 * restores the world instead of leaving it frozen (time) or stuck (difficulty).
 */
final class WorldEnforcer{

    /** @var array<string, true> worlds whose time we stopped */
    private array $timeLocked = [];

    /** @var array<string, int> world => its difficulty before we changed it */
    private array $originalDifficulty = [];

    /** @var array<string, true> lowercase names of players who can fly because of the fly rule */
    private array $flightGranted = [];

    public function __construct(private WorldControl $plugin){}

    public function forgetPlayer(Player $player) : void{
        unset($this->flightGranted[strtolower($player->getName())]);
    }

    /** @param ?string $world null when the defaults changed, which can affect every world */
    public function onRulesChanged(?string $world) : void{
        $worldManager = $this->plugin->getServer()->getWorldManager();
        foreach($worldManager->getWorlds() as $loaded){
            if($world === null || $loaded->getFolderName() === $world){
                $this->applyWorld($loaded);
                foreach($loaded->getPlayers() as $player){
                    $this->applyPlayer($player);
                }
            }
        }
    }

    public function applyAllWorlds() : void{
        foreach($this->plugin->getServer()->getWorldManager()->getWorlds() as $world){
            $this->applyWorld($world);
        }
    }

    public function applyWorld(World $world) : void{
        $rules = $this->plugin->rulesOf($world);
        $name = $world->getFolderName();

        $time = $rules->int(Rule::TIME);
        if($time >= 0){
            if(!$world->stopTime || $world->getTime() % 24000 !== $time){
                $world->setTime($time);
                $world->stopTime();
            }
            $this->timeLocked[$name] = true;
        }elseif(isset($this->timeLocked[$name])){
            unset($this->timeLocked[$name]);
            $world->startTime();
        }

        $difficulty = array_search($rules->string(Rule::DIFFICULTY), Rule::DIFFICULTIES, true);
        if($difficulty !== false){
            $this->originalDifficulty[$name] ??= $world->getDifficulty();
            if($world->getDifficulty() !== $difficulty){
                $world->setDifficulty($difficulty);
            }
        }elseif(isset($this->originalDifficulty[$name])){
            $world->setDifficulty($this->originalDifficulty[$name]);
            unset($this->originalDifficulty[$name]);
        }
    }

    /** Called when a world unloads: nothing to restore any more. */
    public function forgetWorld(World $world) : void{
        unset($this->timeLocked[$world->getFolderName()], $this->originalDifficulty[$world->getFolderName()]);
    }

    /** Hands time and difficulty back to the worlds, e.g. when the plugin is disabled. */
    public function releaseAll() : void{
        $worldManager = $this->plugin->getServer()->getWorldManager();
        // (string): PHP turns a world called "12" into the int key 12
        foreach($this->timeLocked as $name => $_){
            $worldManager->getWorldByName((string) $name)?->startTime();
        }
        foreach($this->originalDifficulty as $name => $difficulty){
            $worldManager->getWorldByName((string) $name)?->setDifficulty($difficulty);
        }
        $this->timeLocked = [];
        $this->originalDifficulty = [];
    }

    /**
     * Brings a player in line with the rules of the world they're in.
     *
     * @param ?World $from the world they just came from, when this is called
     *        because they changed worlds (or null on join / rule change)
     */
    public function applyPlayer(Player $player, ?World $from = null) : void{
        if(!$player->isConnected()) return;
        $rules = $this->plugin->rulesOf($player->getWorld());

        $forced = GameMode::fromString($rules->string(Rule::GAMEMODE));
        if($forced !== null){
            if($player->getGamemode() !== $forced && !$this->plugin->canBypass($player, "gamemode")){
                $player->setGamemode($forced);
            }
        }elseif($from !== null && !$this->plugin->canBypass($player, "gamemode")){
            // Leaving a forced-creative world for a normal one must not keep them in creative
            $previous = GameMode::fromString($this->plugin->rulesOf($from)->string(Rule::GAMEMODE));
            if($previous !== null && $player->getGamemode() === $previous){
                $player->setGamemode($this->plugin->getServer()->getGamemode());
            }
        }

        // Only take away flight we gave, so /fly from another plugin keeps working
        $mode = $player->getGamemode();
        $key = strtolower($player->getName());
        if($mode === GameMode::CREATIVE() || $mode === GameMode::SPECTATOR()){
            unset($this->flightGranted[$key]);
        }elseif($rules->allows(Rule::FLY) || $this->plugin->isBuilder($player)){
            $player->setAllowFlight(true);
            $this->flightGranted[$key] = true;
        }elseif(isset($this->flightGranted[$key])){
            unset($this->flightGranted[$key]);
            $player->setAllowFlight(false);
            $player->setFlying(false);
        }

        if(!$rules->allows(Rule::HUNGER)){
            $hunger = $player->getHungerManager();
            $hunger->setFood($hunger->getMaxFood());
            $hunger->setSaturation(20.0);
        }
    }

    /** The welcome title, subtitle and chat message of the world the player just entered. */
    public function sendWelcome(Player $player) : void{
        $rules = $this->plugin->rulesOf($player->getWorld());
        $translator = $this->plugin->getTranslator();
        $placeholders = [
            "{player}" => $player->getName(),
            "{world}" => $player->getWorld()->getFolderName(),
            "{online}" => (string) count($player->getWorld()->getPlayers()),
        ];

        $title = strtr($rules->string(Rule::WELCOME_TITLE), $placeholders);
        $subtitle = strtr($rules->string(Rule::WELCOME_SUBTITLE), $placeholders);
        if($title !== "" || $subtitle !== ""){
            // a single space keeps the subtitle visible when there's no title
            $player->sendTitle($title === "" ? " " : $translator->display($title, $player, 0), $translator->display($subtitle, $player, 0));
        }

        $message = strtr($rules->string(Rule::WELCOME_MESSAGE), $placeholders);
        if($message !== ""){
            // "\n" typed in a form arrives as the two characters \ and n
            $player->sendMessage($translator->display(str_replace("\\n", "\n", $message), $player, 0));
        }
    }
}
