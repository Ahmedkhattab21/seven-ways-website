<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialReportDefinition extends BaseModel
{
    use HasFactory;

    protected $fillable = ['code', 'name_ar', 'name_en', 'report_type', 'is_active'];

    protected $casts = ['is_system' => 'boolean', 'is_active' => 'boolean'];

    public function sections(): HasMany
    {
        return $this->hasMany(FinancialReportSection::class)->orderBy('sort_order');
    }
}
