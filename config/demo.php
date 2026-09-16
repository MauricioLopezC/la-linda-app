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

];
