<?php

namespace App\Domains\Commerce\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends BaseModel
{
    protected $table = 'user_addresses';

    protected $fillable = [
        'user_id',
        'recipient_name',
        'postal_code',
        'street',
        'number',
        'complement',
        'district',
        'city',
        'state',
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
