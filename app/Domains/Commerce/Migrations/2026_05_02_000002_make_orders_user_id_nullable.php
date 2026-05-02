<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUlid('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // NOTE: reversing this would break existing guest orders with user_id = null
        // Only reverse if you're sure there are no guest orders
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUlid('user_id')->nullable(false)->change();
        });
    }
};
