<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_name', 64)->index();
            $table->timestampTz('event_timestamp')->useCurrent()->index();

            // Identificação
            $table->uuid('session_id')->index();
            $table->uuid('anonymous_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Contexto da visita
            $table->string('device_type', 20)->nullable();
            $table->text('user_agent')->nullable();
            // ip_address criado via DB::statement abaixo (tipo inet nativo do PostgreSQL)
            $table->char('country', 2)->nullable();
            $table->string('city', 100)->nullable();

            // Atribuição (UTM)
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('utm_term', 100)->nullable();
            $table->string('utm_content', 100)->nullable();
            $table->text('referrer')->nullable();
            $table->text('landing_page')->nullable();

            // Payload flexível
            $table->jsonb('properties')->default('{}');

            // Contexto comercial
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->integer('revenue_cents')->nullable();
            $table->char('currency', 3)->default('BRL');
        });

        $isPgsql = DB::getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('ALTER TABLE events ADD COLUMN ip_address inet NULL');
        }

        DB::statement('CREATE INDEX idx_events_name_time ON events (event_name, event_timestamp DESC)');

        if ($isPgsql) {
            DB::statement('CREATE INDEX idx_events_properties ON events USING GIN (properties)');
        }

        DB::statement('CREATE INDEX idx_events_utm ON events (utm_source, utm_medium, utm_campaign)');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
