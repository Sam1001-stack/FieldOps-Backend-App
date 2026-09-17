<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case VoidedByCreditNote = 'voided_by_credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Sent => 'Versendet',
            self::Paid => 'Bezahlt',
            self::Overdue => 'Überfällig',
            self::VoidedByCreditNote => 'Storniert (Gutschrift)',
        };
    }

    public function isImmutable(): bool
    {
        return $this !== self::Draft;
    }
}
