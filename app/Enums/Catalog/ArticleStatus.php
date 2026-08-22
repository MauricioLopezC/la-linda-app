<?php

namespace App\Enums\Catalog;

enum ArticleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
            self::Discontinued => 'Discontinuado',
        };
    }
}
