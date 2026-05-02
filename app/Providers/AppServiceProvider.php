<?php

namespace App\Providers;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Observers\CatalogCacheInvalidationObserver;
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
    public function register(): void
    {
        $this->app->singleton(\App\Domains\Tracking\Services\EventContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Catálogo: ProductAttributeSyncObserver / VariantAttributeSyncObserver são invocados
        // a partir de Product::syncAttributeValues e ProductVariant::syncAttributeValues
        // (alterações só no pivô não disparam Model::observe).

        Product::observe(CatalogCacheInvalidationObserver::class);
        ProductVariant::observe(CatalogCacheInvalidationObserver::class);
        ProductImage::observe(CatalogCacheInvalidationObserver::class);
        Attribute::observe(CatalogCacheInvalidationObserver::class);
        AttributeValue::observe(CatalogCacheInvalidationObserver::class);

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
