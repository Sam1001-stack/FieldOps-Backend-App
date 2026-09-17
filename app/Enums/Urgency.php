<?php

namespace App\Enums;

enum Urgency: string
{
    case Normal = 'normal';
    case Emergency = 'notdienst';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Emergency => 'Notdienst',
        };
    }
}
