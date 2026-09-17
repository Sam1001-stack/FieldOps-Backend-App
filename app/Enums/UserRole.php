<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Owner = 'owner';
    case Office = 'office';
    case Monteur = 'monteur';
    case Customer = 'customer';
    case Accountant = 'accountant';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Plattform',
            self::Owner => 'Inhaber',
            self::Office => 'Büro',
            self::Monteur => 'Monteur',
            self::Customer => 'Kunde',
            self::Accountant => 'Buchhaltung',
        };
    }

    public function isStaff(): bool
    {
        return in_array($this, [self::Owner, self::Office, self::Monteur, self::Accountant], true);
    }
}
