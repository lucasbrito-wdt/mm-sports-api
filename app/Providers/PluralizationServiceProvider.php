<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Pluralizer;

class PluralizationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        Pluralizer::useLanguage('portuguese');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
