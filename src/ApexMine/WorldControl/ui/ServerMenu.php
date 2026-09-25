<?php

declare(strict_types=1);

namespace ApexMine\WorldControl\ui;

use ApexMine\WorldControl\form\CustomForm;
use ApexMine\WorldControl\form\SimpleForm;
use ApexMine\WorldControl\server\ServerSettings;
use pocketmine\player\Player;

/** Server spawn, always-spawn, respawn, /spawn and join/quit messages. */
final class ServerMenu{

    public static function open(Player $player) : void{
        $plugin = Ui::plugin();
        $settings = $plugin->getServerSettings();

        $content = Ui::t($player, "ui.server.content", [
            "spawn" => $settings->describeSpawn() ?? Ui::t($player, "spawn.default-spawn"),
            "always" => Ui::state($player, $settings->isEnabled("always-spawn")),
            "respawn" => Ui::state($player, $settings->isEnabled("respawn-at-spawn")),
            "command" => Ui::state($player, $settings->isEnabled("spawn-command")),
            "join" => Ui::state($player, $settings->isEnabled("join-message")),
            "quit" => Ui::state($player, $settings->isEnabled("quit-message")),
        ]);

        $form = (new SimpleForm(Ui::title($player, "ui.server.title"), Ui::show($player, $content)))
            ->button(
                Ui::button($player, Ui::t($player, "ui.server.settings"), Ui::t($player, "ui.server.settings-sub")),
                fn(Player $p) => self::openSettings($p),
                "textures/ui/icon_setting"
            )
            ->button(
                Ui::button($player, Ui::t($player, "ui.server.set-spawn"), Ui::t($player, "ui.server.set-spawn-sub")),
                function(Player $p) use ($plugin, $settings) : void{
                    $settings->setSpawn($p->getLocation());
                    $plugin->getTranslator()->send($p, "spawn.set", ["spawn" => (string) $settings->describeSpawn()]);
                    self::open($p);
                },
                "textures/items/bed_red"
            )
            ->button(
                Ui::button($player, Ui::t($player, "ui.server.teleport"), Ui::t($player, "ui.server.teleport-sub")),
                function(Player $p) use ($plugin) : void{
                    if($plugin->teleportToSpawn($p)){
                        $plugin->getTranslator()->send($p, "spawn.teleported");
                    }
                },
                "textures/items/ender_pearl"
            );

        if($settings->hasCustomSpawn()){
            $form->button(
                Ui::button($player, Ui::t($player, "ui.server.clear-spawn"), Ui::t($player, "ui.server.clear-spawn-sub")),
                fn(Player $p) => Ui::confirm($p, Ui::t($p, "ui.server.clear-spawn-confirm"), function(Player $p) use ($plugin, $settings) : void{
                    $settings->clearSpawn();
                    $plugin->getTranslator()->send($p, "spawn.cleared");
                    self::open($p);
                }, fn(Player $p) => self::open($p)),
                "textures/ui/refresh_light"
            );
        }

        Ui::backButton($form, $player, fn(Player $p) => MainMenu::open($p))->send($player);
    }

    public static function openSettings(Player $player) : void{
        $plugin = Ui::plugin();
        $settings = $plugin->getServerSettings();

        $form = new CustomForm(Ui::title($player, "ui.server.settings"), function(Player $p, array $data) use ($plugin, $settings) : void{
            $changed = 0;
            foreach(ServerSettings::TOGGLES as $key => $_){
                $value = (bool) ($data[$key] ?? false);
                if($value !== $settings->isEnabled($key)){
                    $settings->setEnabled($key, $value);
                    $changed++;
                }
            }
            $plugin->getTranslator()->send($p, $changed > 0 ? "ui.form.saved" : "ui.form.no-changes", [
                "count" => $changed,
                "target" => $plugin->getTranslator()->raw("ui.server.title", [], $p),
            ]);
            self::open($p);
        });
        $form->onClose(fn(Player $p) => self::open($p));

        foreach(ServerSettings::TOGGLES as $key => $_){
            $form->toggle($key, Ui::show($player, "§f" . Ui::t($player, "server.$key.name") . "\n§7" . Ui::t($player, "server.$key.desc")), $settings->isEnabled($key));
        }
        $form->send($player);
    }
}
