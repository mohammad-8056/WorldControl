<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\rule;

use Closure;

/**
 * Loads and saves worlds.yml.
 *
 * The file has one "defaults" section with a value for every rule, and a
 * "worlds" section where each world lists only the rules it overrides. A world
 * that isn't listed simply follows the defaults, so changing a default (for
 * example locking the time server-wide) reaches every world that hasn't set
 * that rule itself.
 */
final class RuleManager{

    private const HEADER = <<<YAML
# WorldControl - world rules
#
# "defaults" apply to every world. Under "worlds", a world only lists the rules
# it changes; everything else comes from "defaults".
# The easiest way to edit this is in game with /wc. If you edit the file by
# hand, run /wc reload afterwards. A list of every rule is in the README.

YAML;

    /** @var array<string, mixed> every rule key => value */
    private array $defaults = [];

    /** @var array<string, array<string, mixed>> world folder name => [rule key => value] */
    private array $overrides = [];

    /** @var array<string, RuleSet> */
    private array $cache = [];

    /** @param Closure(?string) : void $onChange called with the changed world's name, or null when the defaults changed */
    public function __construct(
        private string $path,
        private Closure $onChange
    ){
        $this->load();
    }

    public function load() : void{
        $data = is_file($this->path) ? yaml_parse_file($this->path) : [];
        $data = is_array($data) ? $data : [];

        $defaults = is_array($data["defaults"] ?? null) ? $data["defaults"] : [];
        $this->defaults = [];
        foreach(Rule::cases() as $rule){
            $this->defaults[$rule->value] = array_key_exists($rule->value, $defaults)
                ? $rule->normalize($defaults[$rule->value])
                : $rule->defaultValue();
        }

        $this->overrides = [];
        foreach(is_array($data["worlds"] ?? null) ? $data["worlds"] : [] as $world => $values){
            if(!is_array($values)) continue;
            foreach($values as $key => $value){
                $rule = Rule::fromKey((string) $key);
                if($rule !== null){
                    $this->overrides[(string) $world][$rule->value] = $rule->normalize($value);
                }
            }
        }
        $this->cache = [];

        if(!is_file($this->path)){
            $this->save();
        }
    }

    public function save() : void{
        $data = [
            "defaults" => $this->defaults,
            // yaml_emit writes an empty array as [], which reads back fine
            "worlds" => $this->overrides,
        ];
        file_put_contents($this->path, self::HEADER . yaml_emit($data, YAML_UTF8_ENCODING));
    }

    public function getRules(string $world) : RuleSet{
        return $this->cache[$world] ??= new RuleSet(($this->overrides[$world] ?? []) + $this->defaults);
    }

    public function getDefaults() : RuleSet{
        return new RuleSet($this->defaults);
    }

    /** @return RuleSet the world's rules, or the defaults when $world is null */
    public function getTarget(?string $world) : RuleSet{
        return $world === null ? $this->getDefaults() : $this->getRules($world);
    }

    public function isOverridden(string $world, Rule $rule) : bool{
        return array_key_exists($rule->value, $this->overrides[$world] ?? []);
    }

    /** @return array<string, mixed> only the rules this world changes */
    public function getOverrides(string $world) : array{
        return $this->overrides[$world] ?? [];
    }

    /** @return list<string> worlds that have at least one override */
    public function getConfiguredWorlds() : array{
        return array_map("strval", array_keys($this->overrides));
    }

    /**
     * @param ?string $world null changes the defaults
     * @param array<string, mixed> $changes rule key => already parsed/normalized value
     */
    public function setMany(?string $world, array $changes) : void{
        if(count($changes) === 0) return;
        foreach($changes as $key => $value){
            if($world === null){
                $this->defaults[$key] = $value;
            }else{
                $this->overrides[$world][$key] = $value;
            }
        }
        $this->changed($world);
    }

    public function set(?string $world, Rule $rule, mixed $value) : void{
        $this->setMany($world, [$rule->value => $value]);
    }

    /** Removes one override so the world follows the default for that rule again. */
    public function unset(string $world, Rule $rule) : void{
        if(!$this->isOverridden($world, $rule)) return;
        unset($this->overrides[$world][$rule->value]);
        if(count($this->overrides[$world]) === 0){
            unset($this->overrides[$world]);
        }
        $this->changed($world);
    }

    /** Drops every override of the world. */
    public function reset(string $world) : void{
        unset($this->overrides[$world]);
        $this->changed($world);
    }

    private function changed(?string $world) : void{
        $this->cache = [];
        $this->save();
        ($this->onChange)($world);
    }
}
