<?php

namespace App\Enums;

/**
 * Whether the enrolment of the year is proven. A situation entered by the central office waits
 * for the establishment's list; confirming that import verifies it automatically. "matched" is
 * retained for historical situations awaiting review. PNES and pre-registration lists are also proof.
 */
enum SituationVerification: string
{
    case Verified = 'verified';
    case Pending = 'pending';
    case Matched = 'matched';

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'Vérifiée',
            self::Pending => 'Pas encore dans la liste de l’établissement',
            self::Matched => 'Trouvée dans la liste — à valider',
        };
    }
}
