<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CostingMethod extends Model
{
    use HasUuids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
    ];

    public function systemSetting(): HasOne
    {
        return $this->hasOne(SystemSetting::class, 'active_costing_method');
    }
}
