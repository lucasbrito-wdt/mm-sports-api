<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('asaas_credit_card_token', 128)->nullable()->after('asaas_payment_id');
            $table->string('asaas_credit_card_brand', 32)->nullable()->after('asaas_credit_card_token');
            $table->string('asaas_credit_card_last4', 4)->nullable()->after('asaas_credit_card_brand');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['asaas_credit_card_token', 'asaas_credit_card_brand', 'asaas_credit_card_last4']);
        });
    }
};
