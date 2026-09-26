<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\lang;

use ApexGaming\WorldControl\libs\libPersianText\Persian;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

/**
 * Every text the plugin shows, in the viewer's own language.
 *
 * Languages are the yml files in plugin_data/WorldControl/lang/. en and fa
 * ship with the plugin; drop another <code>.yml with the same keys to add one.
 * Keys missing from a file fall back to the bundled copy of that language and
 * then to English, so an old edited file keeps working after an update.
 *
 * Text going to a player is passed through libPersianText, which shapes and
 * right-to-lefts Persian (and leaves any other text untouched). The console
 * always gets the raw, logical-order text.
 */
final class Translator{

    public const BUNDLED = ["en", "fa"];
    public const FALLBACK = "en";

    /** @var array<string, array<string, string>> language code => flattened key => text */
    private array $languages = [];

    /** @var array<string, string> lowercase player name => language code */
    private array $playerLanguages = [];

    private string $playersPath;

    public function __construct(
        private PluginBase $plugin,
        private string $defaultLanguage,
        private string $consoleLanguage,
        private bool $autoDetect,
        private int $formLineLength,
        private int $chatLineLength
    ){
        $this->playersPath = $plugin->getDataFolder() . "players.yml";
        $this->loadLanguages();
        $this->loadPlayers();

        if(!$this->hasLanguage($this->defaultLanguage)){
            $plugin->getLogger()->warning("Unknown language '{$this->defaultLanguage}' in config.yml, using " . self::FALLBACK);
            $this->defaultLanguage = self::FALLBACK;
        }
        if(!$this->hasLanguage($this->consoleLanguage)){
            $this->consoleLanguage = self::FALLBACK;
        }
    }

    private function loadLanguages() : void{
        $folder = $this->plugin->getDataFolder() . "lang/";
        foreach(self::BUNDLED as $code){
            $this->plugin->saveResource("lang/$code.yml");
        }

        $this->languages = [];
        foreach(glob($folder . "*.yml") ?: [] as $file){
            $code = strtolower(basename($file, ".yml"));
            $data = yaml_parse_file($file);
            if(!is_array($data)){
                $this->plugin->getLogger()->warning("Could not read language file $file, skipping it");
                continue;
            }
            $this->languages[$code] = self::flatten($data) + $this->bundled($code);
        }
        // English is the last fallback, so it must exist even if its file was deleted
        $this->languages[self::FALLBACK] ??= $this->bundled(self::FALLBACK);
        foreach($this->languages as $code => $messages){
            if($code !== self::FALLBACK){
                $this->languages[$code] += $this->languages[self::FALLBACK];
            }
        }
    }

    /** @return array<string, string> the copy of a language shipped inside the plugin */
    private function bundled(string $code) : array{
        $stream = $this->plugin->getResource("lang/$code.yml");
        if($stream === null) return [];
        $raw = stream_get_contents($stream);
        fclose($stream);
        $data = $raw !== false ? yaml_parse($raw) : false;
        return is_array($data) ? self::flatten($data) : [];
    }

    /**
     * ["rule" => ["pvp" => ["name" => "PvP"]]] => ["rule.pvp.name" => "PvP"]
     *
     * @param array<mixed> $data
     * @return array<string, string>
     */
    private static function flatten(array $data, string $prefix = "") : array{
        $result = [];
        foreach($data as $key => $value){
            $fullKey = $prefix === "" ? (string) $key : "$prefix.$key";
            if(is_array($value)){
                $result += self::flatten($value, $fullKey);
            }else{
                $result[$fullKey] = (string) $value;
            }
        }
        return $result;
    }

    private function loadPlayers() : void{
        $data = is_file($this->playersPath) ? yaml_parse_file($this->playersPath) : [];
        $this->playerLanguages = [];
        foreach(is_array($data) ? $data : [] as $name => $code){
            if(is_string($code)){
                $this->playerLanguages[strtolower((string) $name)] = strtolower($code);
            }
        }
    }

    public function hasLanguage(string $code) : bool{
        return isset($this->languages[strtolower($code)]);
    }

    /** @return array<string, string> language code => its own name, e.g. "fa" => "فارسی" (not shaped) */
    public function getLanguages() : array{
        $result = [];
        foreach($this->languages as $code => $messages){
            $result[$code] = $messages["language.name"] ?? $code;
        }
        return $result;
    }

    public function getLanguage(?CommandSender $viewer) : string{
        if(!$viewer instanceof Player){
            return $this->consoleLanguage;
        }
        $chosen = $this->playerLanguages[strtolower($viewer->getName())] ?? null;
        if($chosen !== null && $this->hasLanguage($chosen)){
            return $chosen;
        }
        if($this->autoDetect){
            // Bedrock locales look like "fa_IR" or "en_US"
            $code = strtolower(explode("_", $viewer->getLocale())[0]);
            if($this->hasLanguage($code)){
                return $code;
            }
        }
        return $this->defaultLanguage;
    }

    public function setPlayerLanguage(Player $player, string $code) : void{
        $this->playerLanguages[strtolower($player->getName())] = strtolower($code);
        file_put_contents($this->playersPath, "# Language each player picked with /wc lang\n" . yaml_emit($this->playerLanguages, YAML_UTF8_ENCODING));
    }

    /**
     * The text as written in the language file, placeholders filled in, NOT
     * shaped. Use it for pieces that go into another text (a rule name inside
     * a message), which is then shaped once as a whole.
     *
     * @param array<string, string|int|float> $params
     */
    public function raw(string $key, array $params = [], ?CommandSender $viewer = null) : string{
        return $this->rawIn($this->getLanguage($viewer), $key, $params);
    }

    /** @param array<string, string|int|float> $params */
    public function rawIn(string $language, string $key, array $params = []) : string{
        $text = $this->languages[$language][$key] ?? $this->languages[self::FALLBACK][$key] ?? null;
        if($text === null){
            $this->plugin->getLogger()->debug("Missing language key: $key");
            return $key;
        }
        $pairs = ["{prefix}" => $this->languages[$language]["prefix"] ?? ""];
        foreach($params as $name => $value){
            $pairs["{" . $name . "}"] = (string) $value;
        }
        return strtr($text, $pairs);
    }

    /**
     * A chat message, ready to send.
     *
     * @param array<string, string|int|float> $params
     */
    public function msg(string $key, array $params = [], ?CommandSender $viewer = null) : string{
        return $this->display($this->raw($key, $params, $viewer), $viewer, $this->chatLineLength);
    }

    /**
     * Text for a form (title, button, label ...), ready to send. Lines are
     * kept shorter than in chat since forms are narrow.
     *
     * @param array<string, string|int|float> $params
     */
    public function ui(string $key, array $params = [], ?CommandSender $viewer = null) : string{
        return $this->display($this->raw($key, $params, $viewer), $viewer, $this->formLineLength);
    }

    /** Shapes already-built text for this viewer: Persian is fixed for players, the console gets it as is. */
    public function display(string $text, ?CommandSender $viewer, int $lineLength = -1) : string{
        if(!$viewer instanceof Player){
            return $text;
        }
        return Persian::fix($text, $lineLength < 0 ? $this->formLineLength : $lineLength);
    }

    public function send(CommandSender $sender, string $key, array $params = []) : void{
        $sender->sendMessage($this->msg($key, $params, $sender));
    }
}
