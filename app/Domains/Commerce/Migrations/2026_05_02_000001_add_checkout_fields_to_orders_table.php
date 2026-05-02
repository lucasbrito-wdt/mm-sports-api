<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('status');
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->string('guest_cpf')->nullable()->after('guest_phone');
            $table->text('asaas_pix_qr_code')->nullable()->after('asaas_payment_id');
            $table->string('asaas_pix_copy_paste', 1024)->nullable()->after('asaas_pix_qr_code');
            $table->timestamp('asaas_pix_expires_at')->nullable()->after('asaas_pix_copy_paste');
            $table->string('asaas_boleto_url', 1024)->nullable()->after('asaas_pix_expires_at');
            $table->string('asaas_boleto_barcode', 256)->nullable()->after('asaas_boleto_url');
            $table->date('asaas_boleto_due_date')->nullable()->after('asaas_boleto_barcode');
            $table->text('notes')->nullable()->after('shipping_address_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method', 'guest_name', 'guest_email', 'guest_phone', 'guest_cpf',
                'asaas_pix_qr_code', 'asaas_pix_copy_paste', 'asaas_pix_expires_at',
                'asaas_boleto_url', 'asaas_boleto_barcode', 'asaas_boleto_due_date',
                'notes',
            ]);
        });
    }
};
