<?php

namespace App\Domains\Shared\Models;

use App\Domains\Shared\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    use HasUlids, TenantScope;
}
