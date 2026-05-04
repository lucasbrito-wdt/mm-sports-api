<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cpf', 14)->nullable()->after('email');
            $table->string('phone', 20)->nullable()->after('cpf');
            $table->string('asaas_customer_id', 64)->nullable()->after('phone');
            $table->index('cpf');
            $table->index('asaas_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['cpf']);
            $table->dropIndex(['asaas_customer_id']);
            $table->dropColumn(['cpf', 'phone', 'asaas_customer_id']);
        });
    }
};
