<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankStatementImportProfile extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'uuid', 'company_id', 'default_scope_key', 'created_by', 'updated_by'];

    protected $casts = [
        'column_mapping' => 'array', 'has_header' => 'boolean', 'is_default' => 'boolean',
        'is_active' => 'boolean', 'balance_tolerance' => 'decimal:4',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
