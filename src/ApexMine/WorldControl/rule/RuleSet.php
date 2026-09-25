<?php

declare(strict_types=1);

namespace ApexMine\WorldControl\rule;

/** The effective rules of one world: its own overrides on top of the defaults. Read-only. */
final class RuleSet{

    /** @param array<string, mixed> $values rule key => value, every rule present */
    public function __construct(private array $values){}

    public function get(Rule $rule) : mixed{
        return $this->values[$rule->value] ?? $rule->defaultValue();
    }

    public function allows(Rule $rule) : bool{
        return $this->get($rule) === true;
    }

    public function int(Rule $rule) : int{
        return (int) $this->get($rule);
    }

    public function string(Rule $rule) : string{
        return (string) $this->get($rule);
    }

    /** @return list<string> */
    public function list(Rule $rule) : array{
        $value = $this->get($rule);
        return is_array($value) ? $value : [];
    }

    /** @return array<string, mixed> */
    public function all() : array{
        return $this->values;
    }
}
