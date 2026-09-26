<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\ui;

use ApexGaming\WorldControl\form\SimpleForm;
use Closure;
use pocketmine\player\Player;

/** Lets any player pick the language WorldControl talks to them in. */
final class LanguageMenu{

    /** @param (Closure(Player) : void)|null $onBack where to go after picking, or null to just close */
    public static function open(Player $player, ?Closure $onBack = null) : void{
        $translator = Ui::plugin()->getTranslator();
        $current = $translator->getLanguage($player);
        $languages = $translator->getLanguages();

        $form = new SimpleForm(
            Ui::title($player, "ui.language.title"),
            Ui::ui($player, "ui.language.content", ["language" => $languages[$current] ?? $current])
        );
        foreach($languages as $code => $name){
            $code = (string) $code;
            $subtitle = $code === $current ? Ui::t($player, "ui.language.current") : $code;
            $form->button(Ui::button($player, $name, $subtitle), function(Player $p) use ($code, $translator, $onBack) : void{
                $translator->setPlayerLanguage($p, $code);
                $translator->send($p, "lang.changed");
                if($onBack !== null){
                    $onBack($p);
                }
            }, "textures/items/book_enchanted");
        }

        if($onBack !== null){
            Ui::backButton($form, $player, $onBack);
        }else{
            $form->button(Ui::button($player, Ui::t($player, "ui.close")), fn() => null, Ui::ICON_CLOSE);
        }
        $form->send($player);
    }
}
