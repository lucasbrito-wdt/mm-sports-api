<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\MigrationServiceProvider::class,
    App\Providers\PluralizationServiceProvider::class,
    App\Providers\ResetPasswordProvider::class,
    //App\Providers\SeedersServiceProvider::class,
    App\Providers\TelescopeServiceProvider::class,
    Tymon\JWTAuth\Providers\LaravelServiceProvider::class,
];
