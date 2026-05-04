<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pedidos legados de guest (user_id null) deixam de ser suportados.
        DB::table('order_status_transitions')->whereIn('order_id', function ($q) {
            $q->select('id')->from('orders')->whereNull('user_id');
        })->delete();
        DB::table('order_items')->whereIn('order_id', function ($q) {
            $q->select('id')->from('orders')->whereNull('user_id');
        })->delete();
        DB::table('orders')->whereNull('user_id')->delete();

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['guest_name', 'guest_email', 'guest_phone', 'guest_cpf']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUlid('user_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUlid('user_id')->nullable()->change();
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->string('guest_cpf')->nullable()->after('guest_phone');
        });
    }
};
