<?php

use App\Domains\ACL\Enums\RoleEnum;

it('inclui permissões de catálogo, marketing, pedidos e UGC na role admin', function () {
    $perms = RoleEnum::Admin->getPermissions();
    expect($perms)->toContain('products list')
        ->and($perms)->toContain('banners list')
        ->and($perms)->toContain('promotions list')
        ->and($perms)->toContain('orders list')
        ->and($perms)->toContain('product reviews moderate')
        ->and($perms)->toContain('size charts list')
        ->and($perms)->toContain('products variants list');
});
