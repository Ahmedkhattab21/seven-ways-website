<?php

namespace App\Services;

use App\Models\Tax;
use Illuminate\Support\Facades\DB;

class TaxService
{
    public function save(Tax $tax, int $companyId, array $data): Tax
    {
        return DB::transaction(function () use ($tax, $companyId, $data) {
            Tax::query()->where('company_id', $companyId)->where('tax_type', $data['tax_type'])
                ->lockForUpdate()->get();

            if ($data['is_default'] ?? false) {
                Tax::query()->where('company_id', $companyId)
                    ->where('tax_type', $data['tax_type'])
                    ->whereKeyNot($tax->getKey())
                    ->update(['is_default' => false]);
            }

            $tax->forceFill([...$data, 'company_id' => $companyId])->save();

            return $tax;
        });
    }
}
