<?php

namespace App\Domains\Product\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Domains\Shared\Models\BaseModel;
use App\Domains\Category\Models\Category;

class Product extends BaseModel
{
    use HasFactory;
    

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['nome', 'descricao', 'preco', 'preco_promocional', 'codigo', 'categoria_id', 'ativo', 'estoque', 'peso'];
    

    /**
     * Get the categorys for this record.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function categorys(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}