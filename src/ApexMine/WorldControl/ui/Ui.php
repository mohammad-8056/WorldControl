<?php

declare(strict_types=1);

namespace ApexMine\WorldControl\ui;

use ApexMine\WorldControl\form\SimpleForm;
use ApexMine\WorldControl\WorldControl;
use Closure;
use pocketmine\player\Player;

/**
 * Small helpers every menu uses, so all forms look the same: two-line
 * buttons (bold title, grey subtitle), paged lists and yes/no questions.
 */
final class Ui{

    /** Forms with many buttons open slowly on Bedrock, so lists are paged. */
    public const PER_PAGE = 10;

    public const ICON_BACK = "textures/items/arrow";
    public const ICON_CLOSE = "textures/blocks/barrier";

    public static function plugin() : WorldControl{
        return WorldControl::getInstance();
    }

    /** Raw text in the player's language (to build bigger texts from). */
    public static function t(Player $player, string $key, array $params = []) : string{
        return self::plugin()->getTranslator()->raw($key, $params, $player);
    }

    /** Display-ready text for a form. */
    public static function ui(Player $player, string $key, array $params = []) : string{
        return self::plugin()->getTranslator()->ui($key, $params, $player);
    }

    /** Makes already-built raw text display-ready for a form. */
    public static function show(Player $player, string $text) : string{
        return self::plugin()->getTranslator()->display($text, $player);
    }

    /** A two-line button: bold title, small grey subtitle. Both are raw text. */
    public static function button(Player $player, string $title, string $subtitle = "") : string{
        return self::show($player, $subtitle === "" ? "§l§8$title" : "§l§8$title\n§r§7$subtitle");
    }

    public static function title(Player $player, string $key, array $params = []) : string{
        return self::show($player, "§l§3" . self::t($player, $key, $params));
    }

    public static function backButton(SimpleForm $form, Player $player, Closure $onBack) : SimpleForm{
        return $form->button(self::button($player, self::t($player, "ui.back")), $onBack, self::ICON_BACK);
    }

    /**
     * A yes/no question. A SimpleForm with two buttons rather than a ModalForm,
     * because some resource packs don't style modal forms and Persian shows as boxes there.
     *
     * @param Closure(Player) : void $onYes
     * @param Closure(Player) : void $onNo also used when the form is closed
     */
    public static function confirm(Player $player, string $question, Closure $onYes, Closure $onNo) : void{
        (new SimpleForm(self::title($player, "ui.confirm.title"), self::show($player, $question)))
            ->button(self::button($player, "§c" . self::t($player, "ui.confirm.yes")), $onYes, "textures/ui/check")
            ->button(self::button($player, self::t($player, "ui.confirm.no")), $onNo, "textures/ui/cancel")
            ->onClose($onNo)
            ->send($player);
    }

    /**
     * A list of buttons shown PER_PAGE at a time, with previous/next and back.
     *
     * @param list<array{0: string, 1: Closure(Player) : void, 2?: ?string}> $items display-ready label, action, icon
     * @param Closure(Player) : void $onBack
     */
    public static function paged(Player $player, string $title, string $content, array $items, int $page, Closure $onBack) : void{
        $pages = max(1, (int) ceil(count($items) / self::PER_PAGE));
        $page = max(0, min($page, $pages - 1));
        $shown = $content;
        if($pages > 1){
            $shown .= ($content === "" ? "" : "\n\n") . self::ui($player, "ui.page", ["page" => $page + 1, "pages" => $pages]);
        }

        $form = new SimpleForm($title, $shown);
        foreach(array_slice($items, $page * self::PER_PAGE, self::PER_PAGE) as $item){
            $form->button($item[0], $item[1], $item[2] ?? null);
        }
        $again = fn(int $to) => fn(Player $p) => self::paged($p, $title, $content, $items, $to, $onBack);
        if($page < $pages - 1){
            $form->button(self::button($player, self::t($player, "ui.next-page")), $again($page + 1), "textures/ui/arrow_right");
        }
        if($page > 0){
            $form->button(self::button($player, self::t($player, "ui.previous-page")), $again($page - 1), "textures/ui/arrow_left");
        }
        self::backButton($form, $player, $onBack)->send($player);
    }

    /** "§aOn" / "§cOff" in the player's language. */
    public static function state(Player $player, bool $on) : string{
        return self::t($player, $on ? "value.on" : "value.off");
    }
}
