<?php

use Illuminate\Support\Facades\Schema;

test('orders table has checkout fields', function () {
    expect(Schema::hasColumn('orders', 'payment_method'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_pix_qr_code'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_pix_copy_paste'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_pix_expires_at'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_boleto_url'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_boleto_barcode'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_boleto_due_date'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'notes'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_credit_card_token'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_credit_card_brand'))->toBeTrue();
    expect(Schema::hasColumn('orders', 'asaas_credit_card_last4'))->toBeTrue();
});

test('orders table no longer has guest fields', function () {
    expect(Schema::hasColumn('orders', 'guest_name'))->toBeFalse();
    expect(Schema::hasColumn('orders', 'guest_email'))->toBeFalse();
    expect(Schema::hasColumn('orders', 'guest_phone'))->toBeFalse();
    expect(Schema::hasColumn('orders', 'guest_cpf'))->toBeFalse();
    expect(Schema::hasTable('guest_customers'))->toBeFalse();
});

test('users table has customer/asaas fields', function () {
    expect(Schema::hasColumn('users', 'cpf'))->toBeTrue();
    expect(Schema::hasColumn('users', 'phone'))->toBeTrue();
    expect(Schema::hasColumn('users', 'asaas_customer_id'))->toBeTrue();
});

test('user_payment_methods table exists', function () {
    expect(Schema::hasTable('user_payment_methods'))->toBeTrue();
    expect(Schema::hasColumn('user_payment_methods', 'asaas_card_token'))->toBeTrue();
    expect(Schema::hasColumn('user_payment_methods', 'is_default'))->toBeTrue();
});
