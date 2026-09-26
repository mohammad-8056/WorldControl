<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\rule;

/** The pages of a world's panel. Each rule belongs to exactly one. */
enum RuleCategory : string{
    case BUILD = "build";
    case COMBAT = "combat";
    case PLAYER = "player";
    case MODE = "mode";
    case ACCESS = "access";
    case MESSAGES = "messages";

    /** @return list<Rule> in display order */
    public function rules() : array{
        return array_values(array_filter(Rule::cases(), fn(Rule $rule) => $rule->category() === $this));
    }

    public function icon() : string{
        return match($this){
            self::BUILD => "textures/items/iron_pickaxe",
            self::COMBAT => "textures/items/iron_sword",
            self::PLAYER => "textures/items/bread",
            self::MODE => "textures/items/clock_item",
            self::ACCESS => "textures/items/door_iron",
            self::MESSAGES => "textures/items/sign",
        };
    }
}
