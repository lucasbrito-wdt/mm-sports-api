<?php

use App\Http\Middleware\ReturnJsonResponseMiddleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Domains/Catalog/Console',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(ReturnJsonResponseMiddleware::class);
        $middleware->append(\App\Http\Middleware\CaptureEventContext::class);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('catalog:refresh-facets')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('catalog:prune-orphan-product-images')->daily()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
