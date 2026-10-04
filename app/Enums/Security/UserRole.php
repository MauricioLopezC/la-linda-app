<?php

namespace App\Enums\Security;

enum UserRole: string
{
    case PersonalInterno = 'personal_interno';
    case Cliente = 'cliente';

    public function label(): string
    {
        return match ($this) {
            self::PersonalInterno => 'Personal Interno',
            self::Cliente => 'Cliente',
        };
    }
}
