<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Wartet auf Freigabe',
            self::Verified => 'Bestätigt',
            self::Rejected => 'Abgelehnt',
        };
    }
}
