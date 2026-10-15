<?php

namespace App\Enums\Security;

use Illuminate\Support\Facades\Route;

enum UserRole: string
{
    case Administrador = 'administrador';
    case Gerente = 'gerente';
    case Compras = 'compras';
    case Deposito = 'deposito';
    case Cajero = 'cajero';
    case Cliente = 'cliente';

    public function label(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Gerente => 'Gerente',
            self::Compras => 'Encargado de compras',
            self::Deposito => 'Encargado de depósito',
            self::Cajero => 'Cajero',
            self::Cliente => 'Cliente',
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

    /**
     * Module Gates granted to the role. The Administrador bypasses this list through Gate::before,
     * so it is the only role that holds ManageUsers.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Administrador => Permission::cases(),
            self::Gerente => [
                Permission::ViewDashboard,
                Permission::ManageWebOrders,
                Permission::ManageCatalog,
                Permission::ManagePricing,
                Permission::ManagePurchases,
                Permission::ManageStock,
                Permission::ManageCustomers,
                Permission::ManageOrganization,
            ],
            self::Compras => [
                Permission::ManageCatalog,
                Permission::ManagePurchases,
                Permission::ManageStock,
            ],
            self::Deposito => [
                Permission::ManageStock,
                Permission::ManageWebOrders,
            ],
            self::Cajero => [
                Permission::OperateCash,
                Permission::ManageCustomers,
            ],
            self::Cliente => [],
        };
    }

    public function grants(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /**
     * Named route of the role's main screen, where it lands after logging in.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Administrador, self::Gerente => 'dashboard',
            self::Compras => 'purchasing.orders.index',
            // The web orders inbox ships with EPIC-17; until then stock is the warehouse's main screen.
            self::Deposito => Route::has('ecommerce.orders.index') ? 'ecommerce.orders.index' : 'inventory.stocks.index',
            self::Cajero => 'sales.cash-sessions.current',
            self::Cliente => 'tienda.home',
        };
    }
}
