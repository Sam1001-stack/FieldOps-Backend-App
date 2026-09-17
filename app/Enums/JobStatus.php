<?php

namespace App\Enums;

enum JobStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Assigned = 'assigned';
    case EnRoute = 'en_route';
    case OnSite = 'on_site';
    case WaitingParts = 'waiting_parts';
    case Completed = 'completed';
    case Invoiced = 'invoiced';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Scheduled => 'Geplant',
            self::Assigned => 'Zugewiesen',
            self::EnRoute => 'Unterwegs',
            self::OnSite => 'Vor Ort',
            self::WaitingParts => 'Wartet auf Teile',
            self::Completed => 'Erledigt',
            self::Invoiced => 'Berechnet',
            self::Cancelled => 'Storniert',
        };
    }

    public function customerLabel(): string
    {
        return match ($this) {
            self::Draft => 'Gesendet',
            self::Scheduled, self::Assigned, self::WaitingParts => 'Geplant',
            self::EnRoute, self::OnSite => 'Unterwegs',
            self::Completed, self::Invoiced => 'Erledigt',
            self::Cancelled => 'Storniert',
        };
    }
}
