<?php

declare(strict_types=1);

namespace ApexMine\WorldControl\ui;

use ApexMine\WorldControl\form\CustomForm;
use ApexMine\WorldControl\form\SimpleForm;
use ApexMine\WorldControl\rule\Rule;
use ApexMine\WorldControl\rule\RuleCategory;
use ApexMine\WorldControl\rule\RuleFormatter;
use ApexMine\WorldControl\rule\RuleType;
use ApexMine\WorldControl\world\WorldActions;
use InvalidArgumentException;
use pocketmine\entity\Location;
use pocketmine\player\Player;

/**
 * The world list, a world's panel, the default rules panel and the rule
 * pages (one form per category). The same pages edit a world ($world set)
 * or the defaults ($world null).
 */
final class WorldMenu{

    // ------------------------------------------------------------ world list

    public static function openList(Player $player, int $page = 0) : void{
        $plugin = Ui::plugin();
        $worldManager = $plugin->getServer()->getWorldManager();
        $manager = $plugin->getRuleManager();

        $names = array_unique([...$plugin->getWorldFolders(), ...$manager->getConfiguredWorlds()]);
        // loaded worlds first, then alphabetical
        usort($names, fn(string $a, string $b) => [!$worldManager->isWorldLoaded($a), strtolower($a)] <=> [!$worldManager->isWorldLoaded($b), strtolower($b)]);

        $items = [];
        foreach($names as $name){
            $world = $worldManager->getWorldByName($name);
            $rules = $manager->getRules($name);
            $badges = [];
            if($world === null) $badges[] = Ui::t($player, "ui.list.badge-unloaded");
            if($rules->allows(Rule::LOCKED)) $badges[] = Ui::t($player, "ui.list.badge-locked");
            $changed = count($manager->getOverrides($name));
            $badges[] = $changed > 0 ? Ui::t($player, "ui.list.badge-custom", ["count" => $changed]) : Ui::t($player, "ui.list.badge-default");

            $subtitle = ($world === null ? "" : Ui::t($player, "ui.list.players", ["count" => count($world->getPlayers())]) . " §8| ") . implode(" §8| ", $badges);
            $items[] = [Ui::button($player, $name, $subtitle), fn(Player $p) => self::openWorld($p, $name), $world === null ? "textures/items/map_filled" : "textures/items/compass_item"];
        }

        Ui::paged($player, Ui::title($player, "ui.list.title"), Ui::ui($player, "ui.list.content"), $items, $page, fn(Player $p) => MainMenu::open($p));
    }

    // ------------------------------------------------------------ one world

    public static function openWorld(Player $player, string $name) : void{
        $plugin = Ui::plugin();
        $manager = $plugin->getRuleManager();
        $worldManager = $plugin->getServer()->getWorldManager();
        $world = $worldManager->getWorldByName($name);
        $rules = $manager->getRules($name);
        $formatter = new RuleFormatter($plugin->getTranslator());

        $content = Ui::t($player, "ui.world.content", [
            "status" => $world === null
                ? Ui::t($player, "ui.world.not-loaded")
                : Ui::t($player, "ui.world.loaded", ["count" => count($world->getPlayers())]),
            "gamemode" => $formatter->value(Rule::GAMEMODE, $rules->get(Rule::GAMEMODE), $player),
            "time" => $formatter->value(Rule::TIME, $rules->get(Rule::TIME), $player),
            "difficulty" => $formatter->value(Rule::DIFFICULTY, $rules->get(Rule::DIFFICULTY), $player),
            "pvp" => Ui::state($player, $rules->allows(Rule::PVP)),
            "build" => Ui::state($player, $rules->allows(Rule::BLOCK_BREAK) && $rules->allows(Rule::BLOCK_PLACE)),
            "locked" => Ui::state($player, $rules->allows(Rule::LOCKED)),
            "custom" => count($manager->getOverrides($name)),
        ]);

        $form = new SimpleForm(Ui::show($player, "§l§3" . $name), Ui::show($player, $content));
        self::addCategoryButtons($form, $player, $name);

        if($worldManager->isWorldGenerated($name)){
            $form->button(Ui::button($player, Ui::t($player, "ui.world.teleport"), Ui::t($player, "ui.world.teleport-sub")), function(Player $p) use ($plugin, $name) : void{
                $target = $plugin->getOrLoadWorld($name);
                if($target !== null){
                    $plugin->teleport($p, Location::fromObject($plugin->safeSpawn($target), $target));
                    $plugin->getTranslator()->send($p, "world.teleported", ["player" => $p->getName(), "world" => $name]);
                }
            }, "textures/items/ender_pearl");
        }
        if($world !== null && $player->getWorld() === $world){
            $form->button(Ui::button($player, Ui::t($player, "ui.world.set-spawn"), Ui::t($player, "ui.world.set-spawn-sub")), function(Player $p) use ($plugin, $name) : void{
                $position = $p->getPosition();
                $p->getWorld()->setSpawnLocation($position);
                $plugin->getTranslator()->send($p, "spawn.world-set", ["world" => $name, "x" => $position->getFloorX(), "y" => $position->getFloorY(), "z" => $position->getFloorZ()]);
                self::openWorld($p, $name);
            }, "textures/items/ender_eye");
        }
        if(count($manager->getOverrides($name)) > 0){
            $form->button(Ui::button($player, Ui::t($player, "ui.world.reset"), Ui::t($player, "ui.world.reset-sub")), function(Player $p) use ($plugin, $name) : void{
                Ui::confirm($p, Ui::t($p, "ui.world.reset-confirm", ["world" => $name]), function(Player $p) use ($plugin, $name) : void{
                    $plugin->getRuleManager()->reset($name);
                    $plugin->getTranslator()->send($p, "set.reset", ["world" => $name]);
                    self::openWorld($p, $name);
                }, fn(Player $p) => self::openWorld($p, $name));
            }, "textures/ui/refresh_light");
        }
        if($world === null && $worldManager->isWorldGenerated($name)){
            $form->button(Ui::button($player, Ui::t($player, "ui.world.load"), Ui::t($player, "ui.world.load-sub")), function(Player $p) use ($plugin, $name) : void{
                [$key, $params] = (new WorldActions($plugin))->load($name);
                $plugin->getTranslator()->send($p, $key, $params);
                self::openWorld($p, $name);
            }, "textures/blocks/grass_side_carried");
        }elseif($world !== null && $world !== $worldManager->getDefaultWorld()){
            $form->button(Ui::button($player, Ui::t($player, "ui.world.unload"), Ui::t($player, "ui.world.unload-sub")), function(Player $p) use ($plugin, $name) : void{
                Ui::confirm($p, Ui::t($p, "ui.world.unload-confirm", ["world" => $name]), function(Player $p) use ($plugin, $name) : void{
                    [$key, $params] = (new WorldActions($plugin))->unload($name);
                    $plugin->getTranslator()->send($p, $key, $params);
                    self::openList($p);
                }, fn(Player $p) => self::openWorld($p, $name));
            }, Ui::ICON_CLOSE);
        }

        Ui::backButton($form, $player, fn(Player $p) => self::openList($p))->send($player);
    }

    public static function openDefaults(Player $player) : void{
        $form = new SimpleForm(Ui::title($player, "ui.defaults.title"), Ui::ui($player, "ui.defaults.content"));
        self::addCategoryButtons($form, $player, null);
        Ui::backButton($form, $player, fn(Player $p) => MainMenu::open($p))->send($player);
    }

    private static function addCategoryButtons(SimpleForm $form, Player $player, ?string $world) : void{
        $manager = Ui::plugin()->getRuleManager();
        foreach(RuleCategory::cases() as $category){
            $subtitle = Ui::t($player, "category.{$category->value}.desc");
            if($world !== null){
                $changed = count(array_filter($category->rules(), fn(Rule $rule) => $manager->isOverridden($world, $rule)));
                if($changed > 0){
                    $subtitle = Ui::t($player, "ui.world.category-changed", ["count" => $changed]);
                }
            }
            $form->button(
                Ui::button($player, Ui::t($player, "category.{$category->value}.name"), $subtitle),
                fn(Player $p) => self::openCategory($p, $world, $category),
                $category->icon()
            );
        }
    }

    private static function back(Player $player, ?string $world) : void{
        $world === null ? self::openDefaults($player) : self::openWorld($player, $world);
    }

    // ------------------------------------------------------------ rule pages

    /** Index in the time dropdown that means "custom, see the input below". */
    private const TIME_CUSTOM = 1 + 6;

    public static function openCategory(Player $player, ?string $world, RuleCategory $category) : void{
        $plugin = Ui::plugin();
        $manager = $plugin->getRuleManager();
        $rules = $manager->getTarget($world);
        $formatter = new RuleFormatter($plugin->getTranslator());

        $title = Ui::show($player, "§l§3" . ($world ?? Ui::t($player, "general.defaults-name")) . " §8- §r§3" . Ui::t($player, "category.{$category->value}.name"));
        $form = new CustomForm($title, function(Player $p, array $data) use ($world, $category, $rules, $formatter, $plugin, $manager) : void{
            $changes = [];
            foreach($category->rules() as $rule){
                try{
                    $value = self::readAnswer($rule, $data, $rules->get($rule));
                }catch(InvalidArgumentException){
                    $plugin->getTranslator()->send($p, "ui.form.invalid", ["rule" => $formatter->name($rule, $p)]);
                    continue;
                }
                if($value !== $rules->get($rule)){
                    $changes[$rule->value] = $value;
                }
            }
            if(count($changes) > 0){
                $manager->setMany($world, $changes);
                $plugin->getTranslator()->send($p, "ui.form.saved", ["count" => count($changes), "target" => $world ?? $plugin->getTranslator()->raw("general.defaults-name", [], $p)]);
            }else{
                $plugin->getTranslator()->send($p, "ui.form.no-changes");
            }
            self::back($p, $world);
        });
        $form->onClose(fn(Player $p) => self::back($p, $world));

        $form->label(Ui::ui($player, $world === null ? "ui.form.intro-defaults" : "ui.form.intro-world", ["world" => (string) $world]));

        foreach($category->rules() as $rule){
            $marker = $world !== null && $manager->isOverridden($world, $rule) ? "§6* " : "";
            $label = Ui::show($player, $marker . "§f" . $formatter->name($rule, $player) . "\n§7" . $formatter->description($rule, $player));
            $value = $rules->get($rule);

            switch($rule->type()){
                case RuleType::BOOL:
                    $form->toggle($rule->value, $label, $value === true);
                    break;
                case RuleType::GAMEMODE:
                case RuleType::DIFFICULTY:
                    $choices = $rule->type() === RuleType::GAMEMODE ? Rule::GAMEMODES : Rule::DIFFICULTIES;
                    $prefix = $rule->type() === RuleType::GAMEMODE ? "gamemode" : "difficulty";
                    $options = [Ui::ui($player, "value.not-forced")];
                    foreach($choices as $choice){
                        $options[] = Ui::ui($player, "$prefix.$choice");
                    }
                    $index = array_search($value, $choices, true);
                    $form->dropdown($rule->value, $label, $options, $index === false ? 0 : $index + 1);
                    break;
                case RuleType::TIME:
                    $options = [Ui::ui($player, "value.time-free")];
                    foreach(Rule::TIME_PRESETS as $preset => $ticks){
                        $options[] = Ui::show($player, Ui::t($player, "time.$preset") . " §8($ticks)");
                    }
                    $options[] = Ui::ui($player, "ui.form.time-custom");
                    $preset = array_search($value, array_values(Rule::TIME_PRESETS), true);
                    $index = $value < 0 ? 0 : ($preset === false ? self::TIME_CUSTOM : $preset + 1);
                    $form->dropdown($rule->value, $label, $options, $index);
                    $form->input($rule->value . ":custom", Ui::ui($player, "ui.form.time-custom-input"), "0 - 23999", $index === self::TIME_CUSTOM ? (string) $value : "");
                    break;
                default: // NUMBER, TEXT, LIST
                    $form->input($rule->value, $label, Ui::t($player, "ui.form.hint-" . strtolower($rule->type()->name)), RuleFormatter::toInput($rule, $value));
            }
        }
        $form->send($player);
    }

    /**
     * @param array<string, mixed> $data the form answers
     * @throws InvalidArgumentException when a typed value doesn't fit the rule
     */
    private static function readAnswer(Rule $rule, array $data, mixed $current) : mixed{
        $answer = $data[$rule->value] ?? null;
        switch($rule->type()){
            case RuleType::BOOL:
                return (bool) $answer;
            case RuleType::GAMEMODE:
                return (int) $answer === 0 ? Rule::NONE : Rule::GAMEMODES[(int) $answer - 1];
            case RuleType::DIFFICULTY:
                return (int) $answer === 0 ? Rule::NONE : Rule::DIFFICULTIES[(int) $answer - 1];
            case RuleType::TIME:
                $index = (int) $answer;
                if($index === 0) return Rule::TIME_UNLOCKED;
                if($index < self::TIME_CUSTOM) return array_values(Rule::TIME_PRESETS)[$index - 1];
                $custom = trim((string) ($data[$rule->value . ":custom"] ?? ""));
                if($custom === "") return $current;
                $ticks = $rule->parse($custom);
                if($ticks < 0) throw new InvalidArgumentException("Custom time must be 0-23999");
                return $ticks;
            default:
                return $rule->parse((string) $answer);
        }
    }
}
