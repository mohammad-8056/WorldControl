<?php

declare(strict_types=1);

// Bundled copy of https://github.com/ApexMine/libPersianText (MIT, see LICENSE next to this file),
// moved into WorldControl's namespace so it can't clash with another plugin shipping the library.
namespace ApexGaming\WorldControl\libs\libPersianText;

/**
 * Makes Persian (Farsi) text display correctly in Minecraft Bedrock, which has
 * no Arabic-script shaping or right-to-left support of its own.
 *
 * Each line is shaped (joined letter forms) and reordered separately, so
 * multi-line messages keep their line order. § color codes, Latin words,
 * numbers and commands stay readable and in place.
 */
final class Persian{

    private const CACHE_LIMIT = 1024;

    private static bool $enabled = true;

    /** @var array<string, string> */
    private static array $cache = [];

    /** Turn processing off (e.g. for a Latin-only language) — fix() then returns text unchanged. */
    public static function setEnabled(bool $enabled) : void{
        self::$enabled = $enabled;
        self::$cache = [];
    }

    /**
     * Shapes and reorders Persian text for display. Text without Persian
     * letters is returned untouched.
     *
     * Call this ONCE, on the final string. Don't insert already-fixed text
     * into another string and fix that again — it would be reversed twice.
     *
     * @param int $maxLineLength when > 0, lines longer than this many visible
     *        characters are first split at word boundaries. Otherwise the
     *        client wraps the already-reversed line itself and the pieces read
     *        bottom-to-top. ~40 suits forms; chat can take more.
     */
    public static function fix(string $text, int $maxLineLength = 0) : string{
        if(!self::$enabled || $text === "" || preg_match("/\p{Arabic}/u", $text) !== 1){
            return $text;
        }
        $cacheKey = $maxLineLength . "\0" . $text;
        if(isset(self::$cache[$cacheKey])){
            return self::$cache[$cacheKey];
        }

        if($maxLineLength > 0){
            $text = self::wrap($text, $maxLineLength);
        }
        $lines = explode("\n", self::normalize($text));
        foreach($lines as $i => $line){
            // ZWNJ (half-space) is only needed to separate letters while shaping; Minecraft's font doesn't draw it
            $lines[$i] = str_replace("\u{200C}", "", PersianTextEngine::process($line));
        }
        $result = implode("\n", $lines);

        if(count(self::$cache) >= self::CACHE_LIMIT){
            self::$cache = [];
        }
        return self::$cache[$cacheKey] = $result;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    public static function fixAll(array $lines, int $maxLineLength = 0) : array{
        return array_map(fn(string $line) => self::fix($line, $maxLineLength), $lines);
    }

    /**
     * Splits lines longer than $maxLength visible characters at word
     * boundaries, on the logical (not yet reversed) text. Each new line
     * carries the active color code; text in (), [] or {} and runs of
     * non-Persian words (like "/loan PlayerName") are never split.
     * fix() calls this for you when given a $maxLineLength.
     */
    public static function wrap(string $text, int $maxLength) : string{
        if($maxLength <= 0) return $text;

        $out = [];
        foreach(explode("\n", $text) as $line){
            if(self::visibleLength($line) <= $maxLength){
                $out[] = $line;
                continue;
            }

            $current = "";
            $color = "";
            foreach(self::unbreakableWords($line) as $word){
                $candidate = $current === "" ? $word : "{$current} {$word}";
                if($current !== "" && self::visibleLength($candidate) > $maxLength){
                    $out[] = $current;
                    $candidate = $color . $word;
                }
                $current = $candidate;
                if(preg_match_all('/§[0-9a-z]/u', $word, $m) > 0){
                    $color = end($m[0]);
                }
            }
            if($current !== ""){
                $out[] = $current;
            }
        }
        return implode("\n", $out);
    }

    /**
     * Prepares text for the engine so colors and brackets don't get moved.
     */
    private static function normalize(string $text) : string{
        // A color code stuck to the end of a word ("word§a next") moves to the start of the next word
        $text = (string) preg_replace("/((?:§.)+)([ \t]+)/u", "$2$1", $text);
        // "[ text ]" becomes "[text]" so it's reversed as one group
        $text = (string) preg_replace("/\[[ \t]+/u", "[", $text);
        return (string) preg_replace("/[ \t]+\]/u", "]", $text);
    }

    /**
     * @return list<string> words, with any bracketed group kept as one "word",
     *         and runs of non-Persian words kept together (the engine treats
     *         them as one left-to-right block too)
     */
    private static function unbreakableWords(string $line) : array{
        $words = [];
        $buffer = "";
        foreach(explode(" ", $line) as $word){
            $buffer = $buffer === "" ? $word : "{$buffer} {$word}";
            $open = preg_match_all('/[(\[{]/u', $buffer);
            $close = preg_match_all('/[)\]}]/u', $buffer);
            if($open <= $close){
                $last = count($words) - 1;
                if($last >= 0 && !self::hasPersian($words[$last]) && !self::hasPersian($buffer)){
                    $words[$last] .= " {$buffer}";
                }else{
                    $words[] = $buffer;
                }
                $buffer = "";
            }
        }
        if($buffer !== ""){
            $words[] = $buffer;
        }
        return $words;
    }

    private static function hasPersian(string $text) : bool{
        return preg_match('/\p{Arabic}/u', $text) === 1;
    }

    private static function visibleLength(string $text) : int{
        return mb_strlen((string) preg_replace('/§./u', "", $text));
    }
}
