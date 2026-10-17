<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Admin User
    |--------------------------------------------------------------------------
    |
    | Seeded on every `db:seed` (including as part of `migrate:fresh --seed`) so
    | resetting the demo database doesn't require recreating a user by hand.
    | Locally these default to a well-known throwaway login; in production set
    | DEMO_ADMIN_EMAIL/DEMO_ADMIN_PASSWORD via Cloud env vars so the credentials
    | never end up hardcoded in the (public) repo.
    |
    */

    'admin_name' => env('DEMO_ADMIN_NAME', 'Test User'),
    'admin_email' => env('DEMO_ADMIN_EMAIL', 'test@example.com'),
    'admin_password' => env('DEMO_ADMIN_PASSWORD', 'password'),

    /*
    |--------------------------------------------------------------------------
    | Demo Staff Users
    |--------------------------------------------------------------------------
    |
    | One user per operational role (gerente, compras, deposito, cajero), seeded
    | as `<role>@<domain>` so each role's landing screen and module access can be
    | demoed. The password falls back to the demo admin one, so production only
    | needs the existing DEMO_ADMIN_PASSWORD unless a separate one is wanted.
    |
    */

    'staff_email_domain' => env('DEMO_STAFF_EMAIL_DOMAIN', 'example.com'),
    'staff_password' => env('DEMO_STAFF_PASSWORD', env('DEMO_ADMIN_PASSWORD', 'password')),

    /*
    |--------------------------------------------------------------------------
    | Sales History
    |--------------------------------------------------------------------------
    |
    | Days of operation simulated by SalesHistorySeeder (counter sales, online
    | orders and supplier restocking up to today), so the management dashboard
    | has history to show. 0 skips it; the test suite does so to stay fast.
    |
    */

    'sales_history_days' => (int) env('DEMO_SALES_HISTORY_DAYS', 183),

];
