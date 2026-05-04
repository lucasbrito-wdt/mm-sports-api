<?php

namespace App\Domains\Commerce\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPaymentMethod extends BaseModel
{
    protected $table = 'user_payment_methods';

    protected $fillable = [
        'user_id',
        'asaas_card_token',
        'brand',
        'last4',
        'holder_name',
        'expiry_month',
        'expiry_year',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'bool',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
