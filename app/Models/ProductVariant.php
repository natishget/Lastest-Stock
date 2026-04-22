<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'color',
        'origin',
        'sku',
        'thickness',
        'size',
    ];

    public const UPDATED_AT = null;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'variant_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'variant_id');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'variant_id');
    }

    public function inventoryCostLayers(): HasMany
    {
        return $this->hasMany(InventoryCostLayer::class, 'variant_id');
    }

    public function inventoryValuations(): HasMany
    {
        return $this->hasMany(InventoryValuation::class, 'variant_id');
    }

    public function cogsEntries(): HasMany
    {
        return $this->hasMany(CogsEntry::class, 'variant_id');
    }
}
