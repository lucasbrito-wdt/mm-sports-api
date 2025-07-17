<?php

namespace App\Providers;

use App\Domains\Shared\Macros\BelongsToManyCreateUpdateOrDelete;
use App\Domains\Shared\Macros\CreateUpdateOrDelete;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\ServiceProvider;
use URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceHttps();

        HasMany::macro('createUpdateOrDelete', function (iterable $records) {
            /** @var HasMany $hasMany */
            $hasMany = $this;

            return (new CreateUpdateOrDelete($hasMany, $records))();
        });

        BelongsToMany::macro('createUpdateOrDeletePivot', function (iterable $records, array $pivotAttributes = []) {
            /** @var BelongsToMany $relation */
            $relation = $this;

            return (new BelongsToManyCreateUpdateOrDelete($relation, $records, $pivotAttributes))();
        });
    }
}
