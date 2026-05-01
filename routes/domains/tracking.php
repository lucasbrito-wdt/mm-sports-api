<?php

use App\Domains\Tracking\Controllers\AnalyticsEventAdminController;
use App\Domains\Tracking\Controllers\AnalyticsEventController;
use App\Domains\Tracking\Controllers\AuditLogAdminController;
use App\Domains\Tracking\Controllers\OrderStatusTransitionAdminController;
use App\Domains\Tracking\Controllers\WebhookInboxAdminController;
use Illuminate\Support\Facades\Route;

Route::post('analytics/events', [AnalyticsEventController::class, 'ingest'])->middleware('throttle:60,1');

Route::post('events/batch', [\App\Domains\Tracking\Controllers\TrackingEventController::class, 'batch'])
    ->middleware('throttle:120,1');

Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::get('analytics-events', [AnalyticsEventAdminController::class, 'index'])->name('analytics.events.index');
    Route::get('analytics-events/{id}', [AnalyticsEventAdminController::class, 'show'])->name('analytics.events.show');

    Route::get('audit-logs', [AuditLogAdminController::class, 'index'])->name('audit.logs.index');
    Route::get('audit-logs/{id}', [AuditLogAdminController::class, 'show'])->name('audit.logs.show');

    Route::get('webhook-inbox', [WebhookInboxAdminController::class, 'index'])->name('webhook.inbox.index');
    Route::get('webhook-inbox/{id}', [WebhookInboxAdminController::class, 'show'])->name('webhook.inbox.show');

    Route::get('order-status-transitions', [OrderStatusTransitionAdminController::class, 'index'])->name('order.transitions.index');
    Route::get('order-status-transitions/{id}', [OrderStatusTransitionAdminController::class, 'show'])->name('order.transitions.show');

    Route::prefix('analytics')->group(function () {
        Route::get('kpis',          [\App\Domains\Tracking\Controllers\Admin\AnalyticsDashboardController::class, 'kpis']);
        Route::get('funnel',        [\App\Domains\Tracking\Controllers\Admin\AnalyticsDashboardController::class, 'funnel']);
        Route::get('revenue-daily', [\App\Domains\Tracking\Controllers\Admin\AnalyticsDashboardController::class, 'revenueDaily']);
        Route::get('top-products',  [\App\Domains\Tracking\Controllers\Admin\AnalyticsDashboardController::class, 'topProducts']);
        Route::get('acquisition',   [\App\Domains\Tracking\Controllers\Admin\AnalyticsDashboardController::class, 'acquisition']);
    });
});
