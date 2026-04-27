<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    public const STATUS_POSTED = 'POSTED';

    public const STATUS_VOIDED = 'VOIDED';

    public const REFERENCE_VOID = 'VOID';

    public const REFERENCE_RETURN = 'RETURN';

    protected $fillable = [
        'customer_name',
        'total_amount',
        'sale_date',
        'status',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    public const UPDATED_AT = null;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
