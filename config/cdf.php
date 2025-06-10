<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default user role
    |--------------------------------------------------------------------------
    |
    | This value is the default user role id that will be assigned to new users
    | when they register.
    |
    | admin = Admin role, user = User role, customer = Customer Role - Check RoleSeeder for more
    |
    */

    'default_user_role_slug' => env('CDF_DEFAULT_ROLE_SLUG', 'user'),

    /*
    |--------------------------------------------------------------------------
    | Delete old access tokens when logged in
    |--------------------------------------------------------------------------
    |
    | This value determines whether or not to delete old access tokens when
    | the users are logged in.
    |
    */

    'delete_previous_access_tokens_on_login' => env('CDF_DELETE_PREVIOUS_ACCESS_TOKENS_ON_LOGIN', false),

    'api_version' => env('CDF_API_VERSION', 'v1'),

    'locales' => [
        'pt_BR',
        'en',
        'es',
    ],

    'tenantTable' => 'tenants',
    'tenantColumn' => 'tenant_id',
    'tenantModels' => [
        Domains\Auth\Models\User::class,
    ],
];
