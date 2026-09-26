<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\form;

use Closure;
use pocketmine\form\Form;
use pocketmine\form\FormValidationException;
use pocketmine\player\Player;

/**
 * A form with inputs. Every element gets a string id, and the submit callback
 * receives the answers keyed by those ids (labels have no answer):
 *
 *     (new CustomForm("Title", function(Player $p, array $data){ $data["name"] ... }))
 *         ->input("name", "Name", "Steve")
 *         ->toggle("pvp", "PvP", true)
 *         ->send($player);
 *
 * Dropdown answers are the chosen option's index. Texts must already be
 * display-ready (Translator::ui()).
 */
final class CustomForm implements Form{

    /** @var list<array<string, mixed>> */
    private array $elements = [];

    /** @var list<?string> element index => id, null for labels */
    private array $ids = [];

    /** @var (Closure(Player) : void)|null */
    private ?Closure $onClose = null;

    /** @param Closure(Player, array<string, mixed>) : void $onSubmit */
    public function __construct(
        private string $title,
        private Closure $onSubmit
    ){}

    public function label(string $text) : self{
        return $this->add(null, ["type" => "label", "text" => $text]);
    }

    public function toggle(string $id, string $text, bool $default) : self{
        return $this->add($id, ["type" => "toggle", "text" => $text, "default" => $default]);
    }

    public function input(string $id, string $text, string $placeholder = "", string $default = "") : self{
        return $this->add($id, ["type" => "input", "text" => $text, "placeholder" => $placeholder, "default" => $default]);
    }

    /** @param list<string> $options */
    public function dropdown(string $id, string $text, array $options, int $default = 0) : self{
        return $this->add($id, ["type" => "dropdown", "text" => $text, "options" => $options, "default" => $default]);
    }

    /** @param Closure(Player) : void $onClose */
    public function onClose(Closure $onClose) : self{
        $this->onClose = $onClose;
        return $this;
    }

    public function send(Player $player) : void{
        $player->sendForm($this);
    }

    /** @param array<string, mixed> $element */
    private function add(?string $id, array $element) : self{
        $this->elements[] = $element;
        $this->ids[] = $id;
        return $this;
    }

    public function jsonSerialize() : array{
        return [
            "type" => "custom_form",
            "title" => $this->title,
            "content" => $this->elements,
        ];
    }

    public function handleResponse(Player $player, $data) : void{
        if($data === null){
            if($this->onClose !== null){
                ($this->onClose)($player);
            }
            return;
        }
        if(!is_array($data)){
            throw new FormValidationException("Expected an array response, got " . gettype($data));
        }

        $answers = [];
        foreach($this->elements as $index => $element){
            $id = $this->ids[$index];
            if($id === null) continue; // a label: the client sends null in its slot
            $value = $data[$index] ?? null;
            $answers[$id] = match($element["type"]){
                "toggle" => is_bool($value) ? $value : throw new FormValidationException("Toggle '$id' expects a bool"),
                "input" => is_string($value) ? $value : throw new FormValidationException("Input '$id' expects a string"),
                "dropdown" => is_int($value) && isset($element["options"][$value]) ? $value : throw new FormValidationException("Dropdown '$id' got an invalid option"),
                default => $value,
            };
        }
        ($this->onSubmit)($player, $answers);
    }
}
