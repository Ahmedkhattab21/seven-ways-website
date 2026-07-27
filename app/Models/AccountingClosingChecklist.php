<?php

namespace App\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingClosingChecklist extends BaseModel
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['completed_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(AccountingClosingChecklistItem::class, 'checklist_id')->orderBy('sort_order');
    }
}
