<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'active_costing_method',
    ];

    public function activeCostingMethod(): BelongsTo
    {
        return $this->belongsTo(CostingMethod::class, 'active_costing_method');
    }
}
