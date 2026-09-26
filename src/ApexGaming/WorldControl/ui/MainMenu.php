<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\ui;

use ApexGaming\WorldControl\form\SimpleForm;
use pocketmine\player\Player;

/** /wc — the panel's home page. */
final class MainMenu{

    public static function open(Player $player) : void{
        $plugin = Ui::plugin();
        $world = $player->getWorld()->getFolderName();
        $loaded = count($plugin->getServer()->getWorldManager()->getWorlds());

        $content = Ui::t($player, "ui.main.content", [
            "player" => $player->getName(),
            "world" => $world,
            "worlds" => $loaded,
            "online" => count($plugin->getServer()->getOnlinePlayers()),
            "alwaysspawn" => Ui::state($player, $plugin->getServerSettings()->isEnabled("always-spawn")),
            "builder" => Ui::state($player, $plugin->isBuilder($player)),
        ]);

        $form = (new SimpleForm(Ui::title($player, "ui.main.title"), Ui::show($player, $content)))
            ->button(
                Ui::button($player, Ui::t($player, "ui.main.this-world"), $world),
                fn(Player $p) => WorldMenu::openWorld($p, $p->getWorld()->getFolderName()),
                "textures/items/compass_item"
            )
            ->button(
                Ui::button($player, Ui::t($player, "ui.main.worlds"), Ui::t($player, "ui.main.worlds-sub", ["count" => $loaded])),
                fn(Player $p) => WorldMenu::openList($p),
                "textures/items/map_filled"
            )
            ->button(
                Ui::button($player, Ui::t($player, "ui.main.defaults"), Ui::t($player, "ui.main.defaults-sub")),
                fn(Player $p) => WorldMenu::openDefaults($p),
                "textures/items/book_writable"
            )
            ->button(
                Ui::button($player, Ui::t($player, "ui.main.server"), Ui::t($player, "ui.main.server-sub")),
                fn(Player $p) => ServerMenu::open($p),
                "textures/items/bed_red"
            )
            ->button(
                Ui::button($player, Ui::t($player, "ui.main.manage"), Ui::t($player, "ui.main.manage-sub")),
                fn(Player $p) => ManageMenu::open($p),
                "textures/blocks/grass_side_carried"
            );

        if($player->hasPermission("worldcontrol.builder")){
            $form->button(
                Ui::button($player, Ui::t($player, "ui.main.builder"), Ui::state($player, $plugin->isBuilder($player))),
                function(Player $p) use ($plugin) : void{
                    $enabled = !$plugin->isBuilder($p);
                    $plugin->setBuilder($p, $enabled);
                    $plugin->getTranslator()->send($p, $enabled ? "builder.on" : "builder.off");
                    self::open($p);
                },
                "textures/items/diamond_pickaxe"
            );
        }
        if((bool) $plugin->getConfig()->get("allow-player-language", true)){
            $languages = $plugin->getTranslator()->getLanguages();
            $form->button(
                Ui::button($player, Ui::t($player, "ui.main.language"), $languages[$plugin->getTranslator()->getLanguage($player)] ?? ""),
                fn(Player $p) => LanguageMenu::open($p, fn(Player $p) => self::open($p)),
                "textures/items/book_enchanted"
            );
        }

        $form->button(Ui::button($player, Ui::t($player, "ui.close")), fn() => null, Ui::ICON_CLOSE)->send($player);
    }
}
