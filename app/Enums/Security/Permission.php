<?php

namespace App\Enums\Security;

/**
 * Module-level Gates (HU-004). Each case value is the Gate name used with `can:<gate>`.
 * Which roles hold each one is defined in UserRole::permissions().
 */
enum Permission: string
{
    case ViewDashboard = 'view-dashboard';
    case ManageWebOrders = 'manage-web-orders';
    case ManageUsers = 'manage-users';
    case OperateCash = 'operate-cash';
    case ManageCatalog = 'manage-catalog';
    case ManagePricing = 'manage-pricing';
    case ManagePurchases = 'manage-purchases';
    case ManageStock = 'manage-stock';
    case ManageCustomers = 'manage-customers';
    case ManageOrganization = 'manage-organization';
}
