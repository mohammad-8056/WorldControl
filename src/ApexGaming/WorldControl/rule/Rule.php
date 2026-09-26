<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\rule;

use InvalidArgumentException;

/**
 * Every rule a world can have. The string value is the key used in worlds.yml,
 * in commands (/wc set lobby pvp off) and in the language files (rule.pvp.name).
 *
 * Boolean rules always mean "is this allowed?": true is vanilla behavior.
 */
enum Rule : string{
    // --- building & world ---
    case BLOCK_BREAK = "break";
    case BLOCK_PLACE = "place";
    case INTERACT = "interact";
    case BUCKET = "bucket";
    case EXPLOSIONS = "explosions";
    case FIRE_SPREAD = "fire-spread";
    case LIQUID_FLOW = "liquid-flow";
    case LEAVES_DECAY = "leaves-decay";
    case CROP_GROWTH = "crop-growth";

    // --- combat ---
    case PVP = "pvp";
    case DAMAGE = "damage";
    case FALL_DAMAGE = "fall-damage";
    case VOID_RESCUE = "void-rescue";

    // --- players ---
    case HUNGER = "hunger";
    case DROP_ITEMS = "drop-items";
    case PICKUP_ITEMS = "pickup-items";
    case FLY = "fly";
    case KEEP_INVENTORY = "keep-inventory";
    case KEEP_XP = "keep-xp";
    case CHAT = "chat";
    case WORLD_CHAT = "world-chat";

    // --- gamemode, difficulty & time ---
    case GAMEMODE = "gamemode";
    case DIFFICULTY = "difficulty";
    case TIME = "time";

    // --- access ---
    case LOCKED = "locked";
    case MAX_PLAYERS = "max-players";
    case BORDER = "border";

    // --- messages & commands ---
    case WELCOME_TITLE = "welcome-title";
    case WELCOME_SUBTITLE = "welcome-subtitle";
    case WELCOME_MESSAGE = "welcome-message";
    case BLOCKED_COMMANDS = "blocked-commands";

    public const NONE = "none";
    public const TIME_UNLOCKED = -1;

    /** Named times of day, in the order they are offered in forms. */
    public const TIME_PRESETS = [
        "sunrise" => 23000,
        "day" => 1000,
        "noon" => 6000,
        "sunset" => 12000,
        "night" => 13000,
        "midnight" => 18000,
    ];

    public const GAMEMODES = ["survival", "creative", "adventure", "spectator"];
    public const DIFFICULTIES = ["peaceful", "easy", "normal", "hard"];

    public function type() : RuleType{
        return match($this){
            self::GAMEMODE => RuleType::GAMEMODE,
            self::DIFFICULTY => RuleType::DIFFICULTY,
            self::TIME => RuleType::TIME,
            self::MAX_PLAYERS, self::BORDER => RuleType::NUMBER,
            self::WELCOME_TITLE, self::WELCOME_SUBTITLE, self::WELCOME_MESSAGE => RuleType::TEXT,
            self::BLOCKED_COMMANDS => RuleType::LIST,
            default => RuleType::BOOL,
        };
    }

    public function category() : RuleCategory{
        return match($this){
            self::BLOCK_BREAK, self::BLOCK_PLACE, self::INTERACT, self::BUCKET, self::EXPLOSIONS,
            self::FIRE_SPREAD, self::LIQUID_FLOW, self::LEAVES_DECAY, self::CROP_GROWTH => RuleCategory::BUILD,
            self::PVP, self::DAMAGE, self::FALL_DAMAGE, self::VOID_RESCUE => RuleCategory::COMBAT,
            self::HUNGER, self::DROP_ITEMS, self::PICKUP_ITEMS, self::FLY, self::KEEP_INVENTORY,
            self::KEEP_XP, self::CHAT, self::WORLD_CHAT => RuleCategory::PLAYER,
            self::GAMEMODE, self::DIFFICULTY, self::TIME => RuleCategory::MODE,
            self::LOCKED, self::MAX_PLAYERS, self::BORDER => RuleCategory::ACCESS,
            self::WELCOME_TITLE, self::WELCOME_SUBTITLE, self::WELCOME_MESSAGE, self::BLOCKED_COMMANDS => RuleCategory::MESSAGES,
        };
    }

    /** Vanilla behavior: a world with only default values behaves exactly like it would without this plugin. */
    public function defaultValue() : mixed{
        return match($this->type()){
            RuleType::BOOL => match($this){
                self::VOID_RESCUE, self::FLY, self::KEEP_INVENTORY, self::KEEP_XP, self::WORLD_CHAT, self::LOCKED => false,
                default => true,
            },
            RuleType::GAMEMODE, RuleType::DIFFICULTY => self::NONE,
            RuleType::TIME => self::TIME_UNLOCKED,
            RuleType::NUMBER => 0,
            RuleType::TEXT => "",
            RuleType::LIST => [],
        };
    }

    /**
     * Turns what an admin typed in a command or form into a stored value.
     *
     * @throws InvalidArgumentException when the input doesn't fit this rule's type
     */
    public function parse(string $input) : mixed{
        $input = trim($input);
        $lower = strtolower($input);
        $clears = ["none", "off", "-", "reset", "unset"];

        switch($this->type()){
            case RuleType::BOOL:
                if(in_array($lower, ["on", "true", "yes", "1", "enable", "enabled", "allow"], true)) return true;
                if(in_array($lower, ["off", "false", "no", "0", "disable", "disabled", "deny"], true)) return false;
                break;

            case RuleType::GAMEMODE:
                if(in_array($lower, $clears, true)) return self::NONE;
                $aliases = ["s" => "survival", "0" => "survival", "c" => "creative", "1" => "creative",
                    "a" => "adventure", "2" => "adventure", "sp" => "spectator", "3" => "spectator", "v" => "spectator"];
                $value = $aliases[$lower] ?? $lower;
                if(in_array($value, self::GAMEMODES, true)) return $value;
                break;

            case RuleType::DIFFICULTY:
                if(in_array($lower, $clears, true)) return self::NONE;
                $aliases = ["p" => "peaceful", "0" => "peaceful", "e" => "easy", "1" => "easy",
                    "n" => "normal", "2" => "normal", "h" => "hard", "3" => "hard"];
                $value = $aliases[$lower] ?? $lower;
                if(in_array($value, self::DIFFICULTIES, true)) return $value;
                break;

            case RuleType::TIME:
                if(in_array($lower, [...$clears, "unlock", "-1"], true)) return self::TIME_UNLOCKED;
                if(isset(self::TIME_PRESETS[$lower])) return self::TIME_PRESETS[$lower];
                if(is_numeric($lower) && (int) $lower >= 0){
                    return (int) $lower % 24000;
                }
                break;

            case RuleType::NUMBER:
                if(in_array($lower, $clears, true)) return 0;
                if(is_numeric($lower) && (int) $lower >= 0) return (int) $lower;
                break;

            case RuleType::TEXT:
                return in_array($lower, ["none", "-"], true) ? "" : $input;

            case RuleType::LIST:
                if(in_array($lower, ["none", "-", ""], true)) return [];
                return self::normalizeList(explode(",", $input));
        }
        throw new InvalidArgumentException("Invalid value '$input' for rule '{$this->value}'");
    }

    /** Makes a value read from worlds.yml safe to use; anything broken falls back to the default. */
    public function normalize(mixed $value) : mixed{
        if(is_string($value) && $this->type() !== RuleType::TEXT){
            try{
                return $this->parse($value);
            }catch(InvalidArgumentException){
                return $this->defaultValue();
            }
        }
        return match($this->type()){
            RuleType::BOOL => is_bool($value) ? $value : $this->defaultValue(),
            RuleType::TIME => is_numeric($value) && (int) $value >= 0 ? (int) $value % 24000 : self::TIME_UNLOCKED,
            RuleType::NUMBER => is_numeric($value) ? max(0, (int) $value) : 0,
            RuleType::TEXT => is_string($value) ? $value : (is_numeric($value) ? (string) $value : ""),
            RuleType::LIST => is_array($value) ? self::normalizeList($value) : [],
            default => $this->defaultValue(),
        };
    }

    /**
     * @param array<mixed> $items
     * @return list<string> lowercase command names without the leading slash
     */
    private static function normalizeList(array $items) : array{
        $items = array_map(fn($item) => strtolower(ltrim(trim((string) $item), "/")), $items);
        return array_values(array_filter($items, fn(string $item) => $item !== ""));
    }

    public static function fromKey(string $key) : ?self{
        return self::tryFrom(strtolower(trim($key)));
    }
}
