<?php

use Illuminate\Support\Facades\Schema;

test('orders table has checkout fields', function () {
    expect(Schema::hasColumn('orders', 'payment_method'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'guest_name'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'guest_email'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'guest_phone'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'guest_cpf'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_pix_qr_code'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_pix_copy_paste'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_pix_expires_at'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_boleto_url'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_boleto_barcode'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_boleto_due_date'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'notes'))->toBeTrue();
});
