<?php

declare(strict_types=1);

namespace ApexGaming\WorldControl\rule;

/** How a rule's value is stored, parsed from a command and edited in a form. */
enum RuleType{
    /** true / false — a toggle in the form. */
    case BOOL;
    /** "none" or a gamemode name — a dropdown. */
    case GAMEMODE;
    /** "none" or a difficulty name — a dropdown. */
    case DIFFICULTY;
    /** -1 (not locked) or a time of day in ticks, 0-23999 — a preset dropdown plus a custom input. */
    case TIME;
    /** A whole number >= 0 where 0 means "off / unlimited" — an input. */
    case NUMBER;
    /** Free text, "" means "nothing" — an input. */
    case TEXT;
    /** A list of words, typed comma-separated — an input. */
    case LIST;
}
