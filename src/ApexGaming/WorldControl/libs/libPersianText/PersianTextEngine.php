<?php

declare(strict_types=1);

// Bundled copy of https://github.com/ApexMine/libPersianText (MIT, see LICENSE next to this file).
namespace ApexGaming\WorldControl\libs\libPersianText;

final class PersianTextEngine{

    private static array $glyphs = [
        "آ" => ["ﺁ", "ﺁ", "ﺂ", "ﺂ"], "ا" => ["ﺍ", "ﺍ", "ﺎ", "ﺎ"],
        "ب" => ["ﺏ", "ﺑ", "ﺒ", "ﺐ"], "پ" => ["ﭖ", "ﭘ", "ﭙ", "ﭗ"],
        "ت" => ["ﺕ", "ﺗ", "ﺘ", "ﺖ"], "ث" => ["ﺙ", "ﺛ", "ﺜ", "ﺚ"], "ج" => ["ﺝ", "ﺟ", "ﺠ", "ﺞ"],
        "چ" => ["ﭺ", "ﭼ", "ﭽ", "ﭻ"], "ح" => ["ﺡ", "ﺣ", "ﺤ", "ﺢ"], "خ" => ["ﺥ", "ﺧ", "ﺨ", "ﺦ"],
        "د" => ["ﺩ", "ﺩ", "ﺪ", "ﺪ"], "ذ" => ["ﺫ", "ﺫ", "ﺬ", "ﺬ"], "ر" => ["ﺭ", "ﺭ", "ﺮ", "ﺮ"],
        "ز" => ["ﺯ", "ﺯ", "ﺰ", "ﺰ"], "ژ" => ["ﮊ", "ﮊ", "ﮋ", "ﮋ"], "س" => ["ﺱ", "ﺳ", "ﺴ", "ﺲ"],
        "ش" => ["ﺵ", "ﺷ", "ﺸ", "ﺶ"], "ص" => ["ﺹ", "ﺻ", "ﺼ", "ﺺ"], "ض" => ["ﺽ", "ﺿ", "ﻀ", "ﺾ"],
        "ط" => ["ﻁ", "ﻃ", "ﻄ", "ﻂ"], "ظ" => ["ﻅ", "ﻇ", "ﻈ", "ﻆ"], "ع" => ["ﻉ", "ﻋ", "ﻌ", "ﻊ"],
        "غ" => ["ﻍ", "ﻏ", "ﻐ", "ﻎ"], "ف" => ["ﻑ", "ﻓ", "ﻔ", "ﻒ"], "ق" => ["ﻕ", "ﻗ", "ﻘ", "ﻖ"],
        "ک" => ["ﮎ", "ﮐ", "ﮑ", "ﮏ"], "گ" => ["ﮒ", "ﮔ", "ﮕ", "ﮓ"], "ل" => ["ﻝ", "ﻟ", "ﻠ", "ﻞ"],
        "م" => ["ﻡ", "ﻣ", "ﻤ", "ﻢ"], "ن" => ["ﻥ", "ﻧ", "ﻨ", "ﻦ"], "و" => ["ﻭ", "ﻭ", "ﻮ", "ﻮ"],
        "ه" => ["ﻩ", "ﻫ", "ﻬ", "ﻪ"], "ی" => ["ﯼ", "ﯾ", "ﯿ", "ﯽ"],
        "ئ" => ["ﺉ", "ﺋ", "ﺌ", "ﺊ"], "ء" => ["ﺀ", "ﺀ", "ﺀ", "ﺀ"],
    ];

    private static array $nonConnectors = ["ا" => 1, "آ" => 1, "د" => 1, "ذ" => 1, "ر" => 1, "ز" => 1, "ژ" => 1, "و" => 1];

    public static function process(string $text): string{
        return self::reversePersianText(self::correctPersianText($text));
    }

    public static function reversePersianText(string $text): string{
        static $hasArabicRegex = null;
        static $numberRegex = null;
        static $symbolRegex = null;

        if ($hasArabicRegex === null) {
            $hasArabicRegex = "/\p{Arabic}/u";
            $numberRegex = "/^\p{N}+$/u";
            $symbolRegex = "/^[><\[\]]+$/";
        }

        $leadingColor = "";
        if (preg_match("/^(?:§.)+/u", $text, $lm)) {
            $leadingColor = $lm[0];
            $text = mb_substr($text, mb_strlen($leadingColor, "UTF-8"), null, "UTF-8");
        }

        $leadingSymbol = "";
        if (preg_match("/^([><\[\]]+)(\s+)/u", $text, $sm)) {
            $leadingSymbol = $sm[1] . ($sm[2] ?? "");
            $text = mb_substr($text, mb_strlen($leadingSymbol, "UTF-8"), null, "UTF-8");
        }

        if (!preg_match($hasArabicRegex, $text)) {
            return $leadingColor . $leadingSymbol . $text;
        }

        preg_match_all("/(?:§.)*(?:\([^)]*\)|\[[^]]*]|\{[^}]*}|<[^>]*>|\s+|[\(\)\[\]{}<>]|[^\s(){}\[\]<>]+)/u", $text, $m);
        $tokens = $m[0];

        $splitPrefix = static function (string $tok): array {
            if (preg_match("/^(?:§.)+/u", $tok, $pm)) {
                $pref = $pm[0];
                $core = mb_substr($tok, mb_strlen($pref, "UTF-8"), null, "UTF-8");
                return [$pref, $core];
            }
            return ["", $tok];
        };

        $merged = [];
        $n = count($tokens);
        $i = 0;
        while ($i < $n) {
            $t = $tokens[$i];

            if (preg_match("/^\s+$/u", $t)) {
                $merged[] = $t;
                $i++;
                continue;
            }

            if (preg_match("/^[(\[{<].*[)\]}>]$/us", $t)) {
                $merged[] = $t;
                $i++;
                continue;
            }

            // فقط کلمه‌های لاتین/عدد پشت سر هم یکی می‌شوند؛ کلمه فارسی یا پرانتز (مثل "5 (سطح تو: 4)")
            // نباید قاطی شود، وگرنه کل تکه حرف‌به‌حرف برعکس می‌شود
            $isLatinRun = static fn(string $c): bool => preg_match("/[A-Za-z0-9]/u", $c) === 1
                && !preg_match($hasArabicRegex, $c)
                && !preg_match("/^[(\[{<]/u", $c);
            [, $core] = $splitPrefix($t);
            if ($isLatinRun($core)) {
                $buf = $t;
                $j = $i + 1;
                while ($j + 1 < $n) {
                    if (!preg_match("/^\s+$/u", $tokens[$j])) {
                        break;
                    }
                    [, $nextCore] = $splitPrefix($tokens[$j + 1]);
                    if (!$isLatinRun($nextCore)) {
                        break;
                    }
                    $buf .= $tokens[$j] . $tokens[$j + 1];
                    $j += 2;
                }
                $merged[] = $buf;
                $i = $j;
                continue;
            }

            $merged[] = $t;
            $i++;
        }

        $lastPrefix = $leadingColor;
        $tokenObjs = [];

        foreach ($merged as $t) {
            if (preg_match("/^\s+$/u", $t)) {
                $tokenObjs[] = ["raw" => $t, "prefix" => "", "core" => $t, "applied" => ""];
                continue;
            }

            [$pref, $core] = $splitPrefix($t);

            if ($pref !== "") {
                $lastPrefix = $pref;
                $applied = $pref;
            } else {
                $applied = $lastPrefix;
            }

            $tokenObjs[] = ["raw" => $t, "prefix" => $pref, "core" => $core, "applied" => $applied];
        }

        $tokenObjs = array_reverse($tokenObjs);

        $outParts = [];
        foreach ($tokenObjs as $tokObj) {
            $raw = $tokObj["raw"];
            $core = $tokObj["core"];
            $colorForToken = $tokObj["applied"];

            if (preg_match("/^\s+$/u", $raw)) {
                $outParts[] = $raw;
                continue;
            }

            if (preg_match("/^(?:§.)*([(\[{<])/u", $raw)) {
                if (preg_match("/^\((.*)\)$/us", $core, $im)) {
                    $inner = self::reversePersianText($im[1]);
                    $outParts[] = $colorForToken . "(" . $inner . ")";
                    continue;
                }
                if (preg_match("/^\[(.*)]$/us", $core, $im)) {
                    $inner = self::reversePersianText($im[1]);
                    $outParts[] = $colorForToken . "[" . $inner . "]";
                    continue;
                }
                if (preg_match("/^\{(.*)}$/us", $core, $im)) {
                    $inner = self::reversePersianText($im[1]);
                    $outParts[] = $colorForToken . "{" . $inner . "}";
                    continue;
                }
                if (preg_match("/^<(.*)>$/us", $core, $im)) {
                    $inner = self::reversePersianText($im[1]);
                    $outParts[] = $colorForToken . "<" . $inner . ">";
                    continue;
                }
            }

            if (preg_match($symbolRegex, $core)) {
                $outParts[] = $colorForToken . $core;
                continue;
            }

            if (!preg_match($hasArabicRegex, $core) && preg_match("/[A-Za-z0-9]/u", $core)) {
                if (preg_match("/^(.*?)([\p{P}\p{S}]+)$/u", $core, $pm)) {
                    [, $word, $pun] = $pm;
                    $core = $pun . $word;
                }
                $outParts[] = $colorForToken . $core;
                continue;
            }

            if (preg_match($hasArabicRegex, $core) && !preg_match($numberRegex, $core)) {
                $units = array_reverse(self::splitUnits($core));
                $outParts[] = $colorForToken . implode("", $units);
                continue;
            }

            $outParts[] = $colorForToken . $core;
        }

        return $leadingSymbol . implode("", $outParts);
    }

    public static function correctPersianText(string $text): string{
        $glyphs = self::$glyphs;
        $nonConnectors = self::$nonConnectors;

        $chars = self::splitUnits($text);
        $count = count($chars);
        $result = [];

        $skipNext = false;
        foreach ($chars as $i => $curr) {
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            $prev = $i > 0 ? $chars[$i - 1] : null;
            $next = $i < $count - 1 ? $chars[$i + 1] : null;

            if ($curr === "ل" && $next === "ا") {
                $hasPrev = $prev !== null;
                $prevGlyph = $hasPrev && isset($glyphs[$prev]);
                $connectsBefore = $prevGlyph && !isset($nonConnectors[$prev]);

                $result[] = $connectsBefore ? "ﻼ" : "ﻻ";
                $skipNext = true;
                continue;
            }

            if (!isset($glyphs[$curr])) {
                $result[] = $curr;
                continue;
            }

            $hasPrev = $prev !== null;
            $hasNext = $next !== null;
            $prevGlyph = $hasPrev && isset($glyphs[$prev]);
            $nextGlyph = $hasNext && isset($glyphs[$next]);

            $connectsBefore = $prevGlyph && !isset($nonConnectors[$prev]);
            $connectsAfter = !isset($nonConnectors[$curr]) && $nextGlyph;

            if ($connectsBefore) {
                $form = $connectsAfter ? 2 : 3;
            } elseif ($connectsAfter) {
                $form = 1;
            } else {
                $form = 0;
            }

            $result[] = $glyphs[$curr][$form];
        }

        return implode("", $result);
    }

    /**
     * Splits text into atomic units: each "§X" color/format code is kept
     * as a single unit so color tags survive glyph shaping and reversal.
     */
    private static function splitUnits(string $text): array{
        $units = [];
        $length = mb_strlen($text, "UTF-8");
        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1, "UTF-8");
            if ($char === "§" && $i + 1 < $length) {
                $units[] = $char . mb_substr($text, $i + 1, 1, "UTF-8");
                $i++;
            } else {
                $units[] = $char;
            }
        }
        return $units;
    }
}