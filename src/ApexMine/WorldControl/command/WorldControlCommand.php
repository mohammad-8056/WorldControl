<?php

declare(strict_types=1);

namespace ApexMine\WorldControl\command;

use ApexMine\WorldControl\rule\Rule;
use ApexMine\WorldControl\rule\RuleCategory;
use ApexMine\WorldControl\rule\RuleFormatter;
use ApexMine\WorldControl\ui\LanguageMenu;
use ApexMine\WorldControl\ui\MainMenu;
use ApexMine\WorldControl\ui\WorldMenu;
use ApexMine\WorldControl\world\WorldActions;
use ApexMine\WorldControl\WorldControl;
use InvalidArgumentException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;

/**
 * /worldcontrol (/wc). Without arguments it opens the panel; every panel
 * action also has a subcommand, so everything works from the console, from
 * command blocks and from other plugins too.
 */
final class WorldControlCommand extends Command implements PluginOwned{
    use PluginOwnedTrait;

    /** Subcommands shown in /wc help, in order. Everything except "lang" and "help" needs worldcontrol.admin. */
    private const SUBCOMMANDS = [
        "help", "ui", "world", "info", "set", "unset", "reset", "time", "gamemode", "rules",
        "alwaysspawn", "setspawn", "setworldspawn", "tp", "list", "create", "load", "unload",
        "builder", "lang", "reload",
    ];
    private const ALIASES = ["menu" => "ui", "gm" => "gamemode", "worlds" => "list", "language" => "lang", "?" => "help"];

    public function __construct(private WorldControl $plugin){
        parent::__construct("worldcontrol", "Manage world rules, time, gamemode and spawn", "/wc help", ["wc"]);
        $this->setPermission("worldcontrol.command");
        $this->owningPlugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
        $translator = $this->plugin->getTranslator();
        $sub = strtolower((string) array_shift($args));
        $sub = self::ALIASES[$sub] ?? $sub;

        if($sub === ""){
            if(!$sender instanceof Player){
                $this->help($sender);
            }elseif($sender->hasPermission("worldcontrol.admin")){
                MainMenu::open($sender);
            }else{
                LanguageMenu::open($sender);
            }
            return true;
        }

        if($sub === "lang"){
            $this->language($sender, $args);
            return true;
        }
        if($sub === "builder"){
            $this->builder($sender);
            return true;
        }
        if(!$sender->hasPermission("worldcontrol.admin")){
            $translator->send($sender, $sub === "help" ? "general.player-help" : "general.no-permission");
            return true;
        }

        match($sub){
            "help" => $this->help($sender),
            "ui" => $this->withPlayer($sender, fn(Player $p) => MainMenu::open($p)),
            "world" => $this->withPlayer($sender, fn(Player $p) => $this->openWorld($p, $args)),
            "info" => $this->info($sender, $args),
            "set" => $this->set($sender, $args),
            "unset" => $this->unset($sender, $args),
            "reset" => $this->reset($sender, $args),
            "time" => $this->shortcut($sender, Rule::TIME, $args),
            "gamemode" => $this->shortcut($sender, Rule::GAMEMODE, $args),
            "rules" => $this->rules($sender),
            "alwaysspawn" => $this->alwaysSpawn($sender, $args),
            "setspawn" => $this->withPlayer($sender, fn(Player $p) => $this->setServerSpawn($p)),
            "setworldspawn" => $this->withPlayer($sender, fn(Player $p) => $this->setWorldSpawn($p)),
            "tp" => $this->teleport($sender, $args),
            "list" => $this->listWorlds($sender),
            "create" => $this->create($sender, $args),
            "load" => $this->worldAction($sender, $args, "load"),
            "unload" => $this->worldAction($sender, $args, "unload"),
            "reload" => $this->reload($sender),
            default => $translator->send($sender, "general.unknown-subcommand", ["sub" => $sub]),
        };
        return true;
    }

    /** @param \Closure(Player) : void $action */
    private function withPlayer(CommandSender $sender, \Closure $action) : void{
        if($sender instanceof Player){
            $action($sender);
        }else{
            $this->plugin->getTranslator()->send($sender, "general.players-only");
        }
    }

    private function usage(CommandSender $sender, string $sub) : void{
        $translator = $this->plugin->getTranslator();
        $sender->sendMessage($translator->msg("general.usage", ["usage" => $translator->raw("help.$sub", [], $sender)], $sender));
    }

    /**
     * "default" (or *, all, server) means the default rules, "here" the
     * sender's world, anything else a world name.
     *
     * @return array{0: bool, 1: ?string} [found, world name or null for the defaults]
     */
    private function target(CommandSender $sender, string $input) : array{
        $lower = strtolower($input);
        if(in_array($lower, ["default", "defaults", "*", "all", "server", "global"], true)){
            return [true, null];
        }
        if(in_array($lower, ["here", "."], true) && $sender instanceof Player){
            return [true, $sender->getWorld()->getFolderName()];
        }
        $world = $this->plugin->resolveWorldName($input);
        if($world === null){
            $this->plugin->getTranslator()->send($sender, "world.not-found", ["world" => $input]);
            return [false, null];
        }
        return [true, $world];
    }

    private function targetName(CommandSender $sender, ?string $world) : string{
        return $world ?? $this->plugin->getTranslator()->raw("general.defaults-name", [], $sender);
    }

    // ------------------------------------------------------------ help

    private function help(CommandSender $sender) : void{
        $translator = $this->plugin->getTranslator();
        $lines = [$translator->raw("help.header", [], $sender)];
        foreach(self::SUBCOMMANDS as $sub){
            $lines[] = $translator->raw("help.line", ["usage" => $translator->raw("help.$sub", [], $sender)], $sender);
        }
        $lines[] = $translator->raw("help.footer", [], $sender);
        $sender->sendMessage($translator->display(implode("\n", $lines), $sender, 0));
    }

    private function rules(CommandSender $sender) : void{
        $translator = $this->plugin->getTranslator();
        $formatter = new RuleFormatter($translator);
        $lines = [$translator->raw("rules.header", [], $sender)];
        foreach(RuleCategory::cases() as $category){
            $lines[] = $translator->raw("rules.category", ["category" => $translator->raw("category.{$category->value}.name", [], $sender)], $sender);
            foreach($category->rules() as $rule){
                $lines[] = $translator->raw("rules.line", [
                    "key" => $rule->value,
                    "name" => $formatter->name($rule, $sender),
                    "values" => $translator->raw("rules.values." . strtolower($rule->type()->name), [], $sender),
                ], $sender);
            }
        }
        $sender->sendMessage($translator->display(implode("\n", $lines), $sender, 0));
    }

    // ------------------------------------------------------------ rules

    private function openWorld(Player $player, array $args) : void{
        if(count($args) === 0){
            WorldMenu::openWorld($player, $player->getWorld()->getFolderName());
            return;
        }
        [$found, $world] = $this->target($player, (string) $args[0]);
        if(!$found) return;
        $world === null ? WorldMenu::openDefaults($player) : WorldMenu::openWorld($player, $world);
    }

    private function info(CommandSender $sender, array $args) : void{
        $translator = $this->plugin->getTranslator();
        if(count($args) === 0 && !$sender instanceof Player){
            $this->usage($sender, "info");
            return;
        }
        [$found, $world] = count($args) === 0 ? [true, $sender->getWorld()->getFolderName()] : $this->target($sender, (string) $args[0]);
        if(!$found) return;

        $manager = $this->plugin->getRuleManager();
        $rules = $manager->getTarget($world);
        $formatter = new RuleFormatter($translator);
        $lines = [$translator->raw("info.header", ["target" => $this->targetName($sender, $world)], $sender)];
        foreach(Rule::cases() as $rule){
            $overridden = $world !== null && $manager->isOverridden($world, $rule);
            $lines[] = $translator->raw($overridden ? "info.line-changed" : "info.line", [
                "name" => $formatter->name($rule, $sender),
                "value" => $formatter->value($rule, $rules->get($rule), $sender),
            ], $sender);
        }
        if($world !== null){
            $lines[] = $translator->raw("info.footer", [], $sender);
        }
        $sender->sendMessage($translator->display(implode("\n", $lines), $sender, 0));
    }

    private function set(CommandSender $sender, array $args) : void{
        if(count($args) < 3){
            $this->usage($sender, "set");
            return;
        }
        [$found, $world] = $this->target($sender, (string) array_shift($args));
        if(!$found) return;
        $rule = Rule::fromKey((string) array_shift($args));
        if($rule === null){
            $this->plugin->getTranslator()->send($sender, "set.unknown-rule", ["list" => "/wc rules"]);
            return;
        }
        $this->apply($sender, $world, $rule, implode(" ", $args));
    }

    /** /wc time <world> <value> and /wc gamemode <world> <mode> */
    private function shortcut(CommandSender $sender, Rule $rule, array $args) : void{
        if(count($args) < 2){
            $this->usage($sender, $rule === Rule::TIME ? "time" : "gamemode");
            return;
        }
        [$found, $world] = $this->target($sender, (string) array_shift($args));
        if($found){
            $this->apply($sender, $world, $rule, implode(" ", $args));
        }
    }

    private function apply(CommandSender $sender, ?string $world, Rule $rule, string $input) : void{
        $translator = $this->plugin->getTranslator();
        try{
            $value = $rule->parse($input);
        }catch(InvalidArgumentException){
            $translator->send($sender, "set.invalid-value", [
                "value" => $input,
                "rule" => $rule->value,
                "values" => $translator->raw("rules.values." . strtolower($rule->type()->name), [], $sender),
            ]);
            return;
        }
        $this->plugin->getRuleManager()->set($world, $rule, $value);
        $formatter = new RuleFormatter($translator);
        $translator->send($sender, "set.changed", [
            "rule" => $formatter->name($rule, $sender),
            "value" => $formatter->value($rule, $value, $sender),
            "target" => $this->targetName($sender, $world),
        ]);
    }

    private function unset(CommandSender $sender, array $args) : void{
        if(count($args) < 2){
            $this->usage($sender, "unset");
            return;
        }
        [$found, $world] = $this->target($sender, (string) $args[0]);
        if(!$found) return;
        $rule = Rule::fromKey((string) $args[1]);
        if($world === null || $rule === null){
            $this->usage($sender, "unset");
            return;
        }
        $this->plugin->getRuleManager()->unset($world, $rule);
        $this->plugin->getTranslator()->send($sender, "set.unset", [
            "rule" => (new RuleFormatter($this->plugin->getTranslator()))->name($rule, $sender),
            "world" => $world,
        ]);
    }

    private function reset(CommandSender $sender, array $args) : void{
        if(count($args) < 1){
            $this->usage($sender, "reset");
            return;
        }
        [$found, $world] = $this->target($sender, (string) $args[0]);
        if(!$found) return;
        if($world === null){
            $this->usage($sender, "reset");
            return;
        }
        $this->plugin->getRuleManager()->reset($world);
        $this->plugin->getTranslator()->send($sender, "set.reset", ["world" => $world]);
    }

    // ------------------------------------------------------------ spawn

    private function alwaysSpawn(CommandSender $sender, array $args) : void{
        $translator = $this->plugin->getTranslator();
        $settings = $this->plugin->getServerSettings();
        switch(strtolower((string) ($args[0] ?? "status"))){
            case "on":
            case "enable":
                $settings->setEnabled("always-spawn", true);
                $translator->send($sender, "spawn.always-on");
                break;
            case "off":
            case "disable":
                $settings->setEnabled("always-spawn", false);
                $translator->send($sender, "spawn.always-off");
                break;
            case "set":
                $this->withPlayer($sender, fn(Player $p) => $this->setServerSpawn($p));
                break;
            case "status":
                $translator->send($sender, "spawn.status", [
                    "state" => $translator->raw($settings->isEnabled("always-spawn") ? "value.on" : "value.off", [], $sender),
                    "spawn" => $settings->describeSpawn() ?? $translator->raw("spawn.default-spawn", [], $sender),
                ]);
                break;
            default:
                $this->usage($sender, "alwaysspawn");
        }
    }

    private function setServerSpawn(Player $player) : void{
        $this->plugin->getServerSettings()->setSpawn($player->getLocation());
        $this->plugin->getTranslator()->send($player, "spawn.set", ["spawn" => (string) $this->plugin->getServerSettings()->describeSpawn()]);
    }

    private function setWorldSpawn(Player $player) : void{
        $position = $player->getPosition();
        $player->getWorld()->setSpawnLocation($position);
        $this->plugin->getTranslator()->send($player, "spawn.world-set", [
            "world" => $player->getWorld()->getFolderName(),
            "x" => $position->getFloorX(), "y" => $position->getFloorY(), "z" => $position->getFloorZ(),
        ]);
    }

    // ------------------------------------------------------------ worlds

    private function teleport(CommandSender $sender, array $args) : void{
        $translator = $this->plugin->getTranslator();
        if(count($args) < 1){
            $this->usage($sender, "tp");
            return;
        }
        $name = $this->plugin->resolveWorldName((string) $args[0]);
        $world = $name === null ? null : $this->plugin->getOrLoadWorld($name);
        if($world === null){
            $translator->send($sender, "world.not-found", ["world" => (string) $args[0]]);
            return;
        }

        if(isset($args[1])){
            $target = $this->plugin->getServer()->getPlayerByPrefix((string) $args[1]);
            if($target === null){
                $translator->send($sender, "general.player-not-found", ["player" => (string) $args[1]]);
                return;
            }
        }elseif($sender instanceof Player){
            $target = $sender;
        }else{
            $this->usage($sender, "tp");
            return;
        }

        $this->plugin->teleport($target, Location::fromObject($this->plugin->safeSpawn($world), $world));
        $translator->send($sender, "world.teleported", ["player" => $target->getName(), "world" => $world->getFolderName()]);
    }

    private function listWorlds(CommandSender $sender) : void{
        $translator = $this->plugin->getTranslator();
        $worldManager = $this->plugin->getServer()->getWorldManager();
        $lines = [$translator->raw("list.header", [], $sender)];
        foreach($this->plugin->getWorldFolders() as $name){
            $world = $worldManager->getWorldByName($name);
            $lines[] = $world === null
                ? $translator->raw("list.unloaded", ["world" => $name], $sender)
                : $translator->raw("list.loaded", ["world" => $name, "players" => count($world->getPlayers())], $sender);
        }
        $sender->sendMessage($translator->display(implode("\n", $lines), $sender, 0));
    }

    private function create(CommandSender $sender, array $args) : void{
        if(count($args) < 1){
            $this->usage($sender, "create");
            return;
        }
        [$key, $params] = (new WorldActions($this->plugin))->create((string) $args[0], (string) ($args[1] ?? "normal"), (string) ($args[2] ?? ""));
        $this->plugin->getTranslator()->send($sender, $key, $params);
    }

    private function worldAction(CommandSender $sender, array $args, string $action) : void{
        if(count($args) < 1){
            $this->usage($sender, $action);
            return;
        }
        $name = $this->plugin->resolveWorldName((string) $args[0]) ?? (string) $args[0];
        $actions = new WorldActions($this->plugin);
        [$key, $params] = $action === "load" ? $actions->load($name) : $actions->unload($name);
        $this->plugin->getTranslator()->send($sender, $key, $params);
    }

    // ------------------------------------------------------------ misc

    private function builder(CommandSender $sender) : void{
        $translator = $this->plugin->getTranslator();
        if(!$sender instanceof Player){
            $translator->send($sender, "general.players-only");
            return;
        }
        if(!$sender->hasPermission("worldcontrol.builder")){
            $translator->send($sender, "general.no-permission");
            return;
        }
        $enabled = !$this->plugin->isBuilder($sender);
        $this->plugin->setBuilder($sender, $enabled);
        $translator->send($sender, $enabled ? "builder.on" : "builder.off");
    }

    private function language(CommandSender $sender, array $args) : void{
        $translator = $this->plugin->getTranslator();
        if(!$sender instanceof Player){
            $translator->send($sender, "general.players-only");
            return;
        }
        if(!(bool) $this->plugin->getConfig()->get("allow-player-language", true)){
            $translator->send($sender, "lang.disabled");
            return;
        }
        if(count($args) === 0){
            LanguageMenu::open($sender);
            return;
        }
        $code = strtolower((string) $args[0]);
        if(!$translator->hasLanguage($code)){
            $translator->send($sender, "lang.unknown", ["list" => implode(", ", array_keys($translator->getLanguages()))]);
            return;
        }
        $translator->setPlayerLanguage($sender, $code);
        $translator->send($sender, "lang.changed");
    }

    private function reload(CommandSender $sender) : void{
        $this->plugin->reload();
        $this->plugin->getTranslator()->send($sender, "general.reloaded");
    }
}
