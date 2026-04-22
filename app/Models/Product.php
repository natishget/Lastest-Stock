<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'base_unit',
    ];

    protected $guarded = [];

    protected $table = 'products';

    public const UPDATED_AT = null;

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
