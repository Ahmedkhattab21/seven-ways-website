<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialReportSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_section_id', 'code', 'name_ar', 'name_en', 'section_type',
        'sign_multiplier', 'sort_order', 'is_total', 'formula',
    ];

    protected $casts = ['sign_multiplier' => 'decimal:4', 'is_total' => 'boolean'];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(FinancialReportDefinition::class, 'financial_report_definition_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(FinancialReportAccountMapping::class);
    }
}
