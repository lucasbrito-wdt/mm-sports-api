<?php

use App\Domains\Integrations\Controllers\AsaasWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/asaas', [AsaasWebhookController::class, 'receive']);
