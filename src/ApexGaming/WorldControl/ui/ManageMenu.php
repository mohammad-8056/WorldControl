<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\ui;

use ApexGaming\WorldControl\form\CustomForm;
use ApexGaming\WorldControl\form\SimpleForm;
use ApexGaming\WorldControl\world\WorldActions;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

/** Creating, loading and unloading worlds. */
final class ManageMenu{

    public static function open(Player $player) : void{
        $plugin = Ui::plugin();
        $worldManager = $plugin->getServer()->getWorldManager();
        $folders = $plugin->getWorldFolders();
        $loaded = array_values(array_filter($folders, fn(string $name) => $worldManager->isWorldLoaded($name)));
        $unloaded = array_values(array_diff($folders, $loaded));

        $form = (new SimpleForm(
            Ui::title($player, "ui.manage.title"),
            Ui::ui($player, "ui.manage.content", ["loaded" => count($loaded), "unloaded" => count($unloaded)])
        ))->button(
            Ui::button($player, Ui::t($player, "ui.manage.create"), Ui::t($player, "ui.manage.create-sub")),
            fn(Player $p) => self::openCreate($p),
            "textures/blocks/grass_side_carried"
        );

        if(count($unloaded) > 0){
            $form->button(
                Ui::button($player, Ui::t($player, "ui.manage.load"), Ui::t($player, "ui.manage.load-sub", ["count" => count($unloaded)])),
                fn(Player $p) => self::openPick($p, "load", $unloaded),
                "textures/items/map_filled"
            );
        }
        $unloadable = array_values(array_filter($loaded, fn(string $name) => $worldManager->getWorldByName($name) !== $worldManager->getDefaultWorld()));
        if(count($unloadable) > 0){
            $form->button(
                Ui::button($player, Ui::t($player, "ui.manage.unload"), Ui::t($player, "ui.manage.unload-sub")),
                fn(Player $p) => self::openPick($p, "unload", $unloadable),
                Ui::ICON_CLOSE
            );
        }

        Ui::backButton($form, $player, fn(Player $p) => MainMenu::open($p))->send($player);
    }

    /** @param list<string> $worlds */
    private static function openPick(Player $player, string $action, array $worlds) : void{
        $plugin = Ui::plugin();
        $items = [];
        foreach($worlds as $name){
            $items[] = [Ui::button($player, $name), function(Player $p) use ($plugin, $action, $name) : void{
                $actions = new WorldActions($plugin);
                [$key, $params] = $action === "load" ? $actions->load($name) : $actions->unload($name);
                $plugin->getTranslator()->send($p, $key, $params);
                self::open($p);
            }, $action === "load" ? "textures/items/map_filled" : "textures/items/compass_item"];
        }
        Ui::paged($player, Ui::title($player, "ui.manage.$action"), Ui::ui($player, "ui.manage.$action-pick"), $items, 0, fn(Player $p) => self::open($p));
    }

    private static function openCreate(Player $player) : void{
        $plugin = Ui::plugin();
        $generators = WorldActions::getGenerators();

        $form = new CustomForm(Ui::title($player, "ui.manage.create"), function(Player $p, array $data) use ($plugin, $generators) : void{
            $name = trim((string) $data["name"]);
            [$key, $params] = (new WorldActions($plugin))->create($name, $generators[$data["generator"]] ?? "normal", (string) $data["seed"]);
            $plugin->getTranslator()->send($p, $key, $params);
            if($key !== "world.created"){
                self::openCreate($p);
                return;
            }
            if($data["teleport"] === true){
                // give the spawn chunks a moment to generate
                $plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($plugin, $p, $name) : void{
                    $world = $plugin->getServer()->getWorldManager()->getWorldByName($name);
                    if($p->isConnected() && $world !== null){
                        $plugin->teleport($p, Location::fromObject($plugin->safeSpawn($world), $world));
                    }
                }), 40);
            }
        });
        $form->onClose(fn(Player $p) => self::open($p));

        $default = array_search("normal", $generators, true);
        $form->label(Ui::ui($player, "ui.manage.create-intro"))
            ->input("name", Ui::ui($player, "ui.manage.create-name"), "my_world")
            ->dropdown("generator", Ui::ui($player, "ui.manage.create-generator"), $generators, $default === false ? 0 : $default)
            ->input("seed", Ui::ui($player, "ui.manage.create-seed"), Ui::t($player, "ui.manage.create-seed-hint"))
            ->toggle("teleport", Ui::ui($player, "ui.manage.create-teleport"), true)
            ->send($player);
    }
}
