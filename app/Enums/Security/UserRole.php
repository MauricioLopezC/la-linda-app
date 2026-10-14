<?php

namespace App\Enums\Security;

enum UserRole: string
{
    case Administrador = 'administrador';
    case Gerente = 'gerente';
    case Compras = 'compras';
    case Deposito = 'deposito';
    case Cajero = 'cajero';
    case Cliente = 'cliente';
    case PersonalInterno = 'personal_interno';

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Gerente => 'Gerente',
            self::Compras => 'Encargado de compras',
            self::Deposito => 'Encargado de depósito',
            self::Cajero => 'Cajero / Vendedor',
            self::Cliente => 'Cliente',
            self::PersonalInterno => 'Personal Interno',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function internalRoles(): array
    {
        return [
            self::Administrador->value => self::Administrador->label(),
            self::Gerente->value => self::Gerente->label(),
            self::Compras->value => self::Compras->label(),
            self::Deposito->value => self::Deposito->label(),
            self::Cajero->value => self::Cajero->label(),
        ];
    }

    public function isInternal(): bool
    {
        return $this !== self::Cliente;
    }
}
