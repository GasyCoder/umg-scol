<?php

namespace App\Enums;

/** "Code de redoublement" of the establishment file: situation of the student in the declared level. */
enum RepeatCode: string
{
    case New = 'N';
    case Repeating = 'R';
    case TripleOrMore = 'T+';

    public function label(): string
    {
        return match ($this) {
            self::New => 'N (Nouveau)',
            self::Repeating => 'R (Redoublant)',
            self::TripleOrMore => 'T+ (Triplant ou plus)',
        };
    }

    /** Accepts the code alone or the full label of the template ("N (Nouveau)"), any case. */
    public static function parse(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }
        $code = strtoupper(trim((string) preg_replace('/\s*\(.*$/u', '', trim($value))));

        return self::tryFrom($code);
    }
}
