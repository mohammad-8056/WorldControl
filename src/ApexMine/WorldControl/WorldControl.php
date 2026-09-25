<?php

declare(strict_types=1);

namespace ApexMine\WorldControl;

use ApexMine\WorldControl\command\SpawnCommand;
use ApexMine\WorldControl\command\WorldControlCommand;
use ApexMine\WorldControl\lang\Translator;
use ApexMine\WorldControl\listener\PlayerListener;
use ApexMine\WorldControl\listener\ProtectionListener;
use ApexMine\WorldControl\listener\WorldListener;
use ApexMine\WorldControl\rule\RuleManager;
use ApexMine\WorldControl\rule\RuleSet;
use ApexMine\WorldControl\server\ServerSettings;
use ApexMine\WorldControl\world\WorldEnforcer;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldException;

final class WorldControl extends PluginBase{

    private static self $instance;

    private Translator $translator;
    private ServerSettings $serverSettings;
    private RuleManager $rules;
    private WorldEnforcer $enforcer;

    /** @var array<string, true> lowercase names of players in builder mode */
    private array $builders = [];

    /** @var array<string, float> "player:key" => microtime of the last "you can't do that" message */
    private array $denyCooldowns = [];

    /** @var array<string, true> players being teleported by teleport() right now */
    private array $trustedTeleports = [];

    public static function getInstance() : self{
        return self::$instance;
    }

    protected function onLoad() : void{
        self::$instance = $this;
    }

    protected function onEnable() : void{
        $this->saveDefaultConfig();
        $this->translator = $this->createTranslator();
        $this->serverSettings = new ServerSettings($this->getDataFolder() . "server.yml");
        $this->enforcer = new WorldEnforcer($this);
        $this->rules = new RuleManager($this->getDataFolder() . "worlds.yml", fn(?string $world) => $this->enforcer->onRulesChanged($world));

        $pluginManager = $this->getServer()->getPluginManager();
        $pluginManager->registerEvents(new ProtectionListener($this), $this);
        $pluginManager->registerEvents(new PlayerListener($this), $this);
        $pluginManager->registerEvents(new WorldListener($this), $this);

        $this->getServer()->getCommandMap()->registerAll($this->getName(), [
            new WorldControlCommand($this),
            new SpawnCommand($this, array_values(array_map("strval", (array) $this->getConfig()->get("spawn-aliases", [])))),
        ]);

        // Keeps locked times locked even if another plugin or /time moves them
        $interval = max(1, (int) $this->getConfig()->get("enforce-interval", 5)) * 20;
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(fn() => $this->enforcer->applyAllWorlds()), $interval);

        $this->enforcer->applyAllWorlds();
    }

    protected function onDisable() : void{
        if(isset($this->enforcer)){
            $this->enforcer->releaseAll();
        }
    }

    private function createTranslator() : Translator{
        $config = $this->getConfig();
        return new Translator(
            $this,
            strtolower((string) $config->get("language", "en")),
            strtolower((string) $config->get("console-language", "en")),
            (bool) $config->get("auto-detect-language", true),
            (int) $config->getNested("persian.form-line-length", 40),
            (int) $config->getNested("persian.chat-line-length", 0)
        );
    }

    /** Re-reads config.yml, the language files, server.yml and worlds.yml, then re-applies everything. */
    public function reload() : void{
        $this->reloadConfig();
        $this->translator = $this->createTranslator();
        $this->serverSettings->load();
        $this->rules->load();
        $this->enforcer->applyAllWorlds();
        foreach($this->getServer()->getOnlinePlayers() as $player){
            $this->enforcer->applyPlayer($player);
        }
    }

    public function getTranslator() : Translator{
        return $this->translator;
    }

    public function getServerSettings() : ServerSettings{
        return $this->serverSettings;
    }

    public function getRuleManager() : RuleManager{
        return $this->rules;
    }

    public function getEnforcer() : WorldEnforcer{
        return $this->enforcer;
    }

    public function rulesOf(World $world) : RuleSet{
        return $this->rules->getRules($world->getFolderName());
    }

    // ---------------------------------------------------------------- bypass

    public function isBuilder(Player $player) : bool{
        return isset($this->builders[strtolower($player->getName())]);
    }

    public function setBuilder(Player $player, bool $builder) : void{
        if($builder){
            $this->builders[strtolower($player->getName())] = true;
        }else{
            unset($this->builders[strtolower($player->getName())]);
        }
        $this->enforcer->applyPlayer($player);
    }

    /**
     * @param string $what build, combat, gamemode, commands, chat, border or enter
     *        (matches the worldcontrol.bypass.<what> permissions)
     */
    public function canBypass(Player $player, string $what) : bool{
        return $this->isBuilder($player)
            || $player->hasPermission("worldcontrol.bypass.$what")
            || $player->hasPermission("worldcontrol.bypass.all");
    }

    /** Tells a player an action was blocked, at most once per second per kind of action. */
    public function deny(Player $player, string $key, array $params = []) : void{
        $mode = strtolower((string) $this->getConfig()->get("deny-message", "actionbar"));
        if($mode === "none") return;

        $cooldownKey = strtolower($player->getName()) . ":" . $key;
        $now = microtime(true);
        if(($this->denyCooldowns[$cooldownKey] ?? 0.0) > $now - 1.0) return;
        $this->denyCooldowns[$cooldownKey] = $now;

        // one line only: the action bar can't show more
        $text = $this->translator->display($this->translator->raw($key, $params, $player), $player, 0);
        match($mode){
            "chat" => $player->sendMessage($text),
            "tip" => $player->sendTip($text),
            default => $player->sendActionBarMessage($text),
        };
    }

    /**
     * Teleports without the locked / full world checks: used for the server
     * spawn and for admins sending someone somewhere on purpose.
     */
    public function teleport(Player $player, Position $to) : bool{
        $name = strtolower($player->getName());
        $this->trustedTeleports[$name] = true;
        try{
            return $player->teleport($to);
        }finally{
            unset($this->trustedTeleports[$name]);
        }
    }

    public function isTrustedTeleport(Player $player) : bool{
        return isset($this->trustedTeleports[strtolower($player->getName())]);
    }

    /** @return bool false when there's no spawn to go to (no default world) */
    public function teleportToSpawn(Player $player) : bool{
        $spawn = $this->serverSettings->getSpawn();
        return $spawn !== null && $this->teleport($player, $spawn);
    }

    public function forgetPlayer(Player $player) : void{
        $name = strtolower($player->getName());
        unset($this->builders[$name]);
        $this->enforcer->forgetPlayer($player);
        foreach($this->denyCooldowns as $key => $_){
            if(str_starts_with($key, "$name:")){
                unset($this->denyCooldowns[$key]);
            }
        }
    }

    // ---------------------------------------------------------------- worlds

    /**
     * Finds a world by name, ignoring case: loaded worlds first, then world
     * folders on disk, then worlds that only exist in worlds.yml.
     *
     * @return string|null the world's real folder name
     */
    public function resolveWorldName(string $name) : ?string{
        $lower = strtolower(trim($name));
        foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
            if(strtolower($world->getFolderName()) === $lower) return $world->getFolderName();
        }
        foreach([...$this->getWorldFolders(), ...$this->rules->getConfiguredWorlds()] as $folder){
            if(strtolower($folder) === $lower) return $folder;
        }
        return null;
    }

    /** @return list<string> every generated world in the worlds/ folder, loaded or not */
    public function getWorldFolders() : array{
        $worldManager = $this->getServer()->getWorldManager();
        $result = [];
        foreach(scandir($this->getServer()->getDataPath() . "worlds") ?: [] as $entry){
            if($entry !== "." && $entry !== ".." && $worldManager->isWorldGenerated($entry)){
                $result[] = $entry;
            }
        }
        return $result;
    }

    /** A safe place to stand at the world's spawn, or the spawn itself while its terrain isn't generated yet. */
    public function safeSpawn(World $world) : Position{
        try{
            return $world->getSafeSpawn();
        }catch(WorldException){
            return $world->getSpawnLocation();
        }
    }

    /** Loads the world if needed. */
    public function getOrLoadWorld(string $name) : ?World{
        $worldManager = $this->getServer()->getWorldManager();
        if(!$worldManager->isWorldLoaded($name) && $worldManager->isWorldGenerated($name)){
            $worldManager->loadWorld($name, true);
        }
        return $worldManager->getWorldByName($name);
    }
}
