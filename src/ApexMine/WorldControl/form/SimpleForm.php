<?php

declare(strict_types=1);

namespace ApexMine\WorldControl\form;

use Closure;
use pocketmine\form\Form;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

/**
 * A list of buttons. Each button carries its own action, so there is no
 * index bookkeeping in the menus:
 *
 *     (new SimpleForm("Title", "Text"))
 *         ->button("Teleport", fn(Player $p) => ..., "textures/items/ender_pearl")
 *         ->send($player);
 *
 * Titles, text and labels must already be display-ready (Translator::ui()).
 */
final class SimpleForm implements Form{

    /** @var list<array{text: string, image: ?string, action: Closure(Player) : void}> */
    private array $buttons = [];

    /** @var (Closure(Player) : void)|null */
    private ?Closure $onClose = null;

    public function __construct(
        private string $title,
        private string $content = ""
    ){}

    /** @param Closure(Player) : void $action */
    public function button(string $text, Closure $action, ?string $image = null) : self{
        $this->buttons[] = ["text" => $text, "image" => $image, "action" => $action];
        return $this;
    }

    /** @param Closure(Player) : void $onClose run when the player closes the form without picking */
    public function onClose(Closure $onClose) : self{
        $this->onClose = $onClose;
        return $this;
    }

    public function send(Player $player) : void{
        $player->sendForm($this);
    }

    public function jsonSerialize() : array{
        $buttons = [];
        foreach($this->buttons as $button){
            $entry = ["text" => $button["text"]];
            if($button["image"] !== null){
                $entry["image"] = [
                    "type" => str_starts_with($button["image"], "http") ? "url" : "path",
                    "data" => $button["image"],
                ];
            }
            $buttons[] = $entry;
        }
        return [
            "type" => "form",
            "title" => $this->title,
            "content" => $this->content,
            "buttons" => $buttons,
        ];
    }

    public function handleResponse(Player $player, $data) : void{
        if($data === null){
            if($this->onClose !== null){
                ($this->onClose)($player);
            }
            return;
        }
        if(!is_int($data) || !isset($this->buttons[$data])){
            throw new FormValidationException("Invalid button index " . var_export($data, true));
        }
        ($this->buttons[$data]["action"])($player);
    }
}
