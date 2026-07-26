<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethodAccountMapping extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'branch_id', 'payment_method_id', 'account_id', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
