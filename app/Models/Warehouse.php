<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'location',
    ];

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function inventoryCostLayers(): HasMany
    {
        return $this->hasMany(InventoryCostLayer::class);
    }

    public function inventoryValuations(): HasMany
    {
        return $this->hasMany(InventoryValuation::class);
    }
}
