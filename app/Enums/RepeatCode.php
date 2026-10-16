<?php

namespace App\Enums;

use Illuminate\Support\Str;

/** "Code de redoublement" of the establishment file: situation of the student in the declared level. */
enum RepeatCode: string
{
    case New = 'N';
    case Passing = 'P';
    case Repeating = 'R';
    case TripleOrMore = 'T+';

    public function label(): string
    {
        return match ($this) {
            self::New => 'N (Nouveau)',
            self::Passing => 'P (Passant)',
            self::Repeating => 'R (Redoublant)',
            self::TripleOrMore => 'T+ (Triplant ou plus)',
        };
    }

    /** Accepts the code, the full label of the template ("N (Nouveau)") or the word alone ("PASSANT"), any case. */
    public static function parse(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }
        $code = strtoupper(trim((string) preg_replace('/\s*\(.*$/u', '', trim($value))));

        return self::tryFrom($code) ?? match (strtoupper(Str::ascii($code))) {
            'NOUVEAU', 'NOUVELLE' => self::New,
            'PASSANT', 'PASSANTE' => self::Passing,
            'REDOUBLANT', 'REDOUBLANTE' => self::Repeating,
            'TRIPLANT', 'TRIPLANTE', 'T' => self::TripleOrMore,
            default => null,
        };
    }
}
