<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostCenter extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id', 'parent_cost_center_id', 'code', 'name_ar', 'name_en', 'description',
        'cost_center_type', 'is_header', 'is_posting', 'manager_employee_id', 'is_active',
    ];

    protected $casts = [
        'is_header' => 'boolean', 'is_posting' => 'boolean', 'is_system' => 'boolean',
        'is_active' => 'boolean', 'level' => 'integer',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_cost_center_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_cost_center_id');
    }
}
