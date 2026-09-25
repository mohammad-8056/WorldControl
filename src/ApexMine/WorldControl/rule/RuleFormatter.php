<?php

declare(strict_types=1);

namespace ApexMine\WorldControl\rule;

use ApexMine\WorldControl\lang\Translator;
use pocketmine\command\CommandSender;

/** Rule values as a viewer reads them ("§aOn", "Creative", "Noon (6000)" ...). Returns raw, unshaped text. */
final class RuleFormatter{

    public function __construct(private Translator $translator){}

    public function name(Rule $rule, ?CommandSender $viewer) : string{
        return $this->translator->raw("rule.{$rule->value}.name", [], $viewer);
    }

    public function description(Rule $rule, ?CommandSender $viewer) : string{
        return $this->translator->raw("rule.{$rule->value}.desc", [], $viewer);
    }

    public function value(Rule $rule, mixed $value, ?CommandSender $viewer) : string{
        $t = fn(string $key, array $params = []) => $this->translator->raw($key, $params, $viewer);

        return match($rule->type()){
            RuleType::BOOL => $value === true ? $t("value.on") : $t("value.off"),
            RuleType::GAMEMODE => $value === Rule::NONE ? $t("value.not-forced") : $t("gamemode.$value"),
            RuleType::DIFFICULTY => $value === Rule::NONE ? $t("value.not-forced") : $t("difficulty.$value"),
            RuleType::TIME => $this->time((int) $value, $viewer),
            RuleType::NUMBER => (int) $value === 0
                ? $t($rule === Rule::MAX_PLAYERS ? "value.unlimited" : "value.off")
                : ($rule === Rule::BORDER ? $t("value.blocks", ["count" => (int) $value]) : (string) $value),
            RuleType::TEXT => $value === "" ? $t("value.empty") : self::shorten((string) $value),
            RuleType::LIST => is_array($value) && count($value) > 0 ? self::shorten(implode(", ", $value)) : $t("value.empty"),
        };
    }

    public function time(int $ticks, ?CommandSender $viewer) : string{
        if($ticks < 0){
            return $this->translator->raw("value.time-free", [], $viewer);
        }
        $preset = array_search($ticks, Rule::TIME_PRESETS, true);
        return $preset === false
            ? $this->translator->raw("value.time-ticks", ["ticks" => $ticks], $viewer)
            : $this->translator->raw("value.time-preset", ["name" => $this->translator->raw("time.$preset", [], $viewer), "ticks" => $ticks], $viewer);
    }

    /** What to type in a command/form input to get this value back. */
    public static function toInput(Rule $rule, mixed $value) : string{
        return match($rule->type()){
            RuleType::BOOL => $value === true ? "on" : "off",
            RuleType::LIST => is_array($value) ? implode(", ", $value) : "",
            default => (string) $value,
        };
    }

    private static function shorten(string $text) : string{
        $text = str_replace("\n", " ", $text);
        return mb_strlen($text) > 32 ? mb_substr($text, 0, 30) . "..§r" : $text . "§r";
    }
}
