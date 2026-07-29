<?php

namespace App\Http\Requests;

class CashOverShortActionRequest extends AccountingFormRequest
{
    public function rules(): array
    {
        $creating = $this->routeIs('treasury.cash-counts.adjustment');
        $reversing = $this->route('action') === 'reverse';

        return $this->withProtected([
            'description' => [$creating ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
            'reason' => [$reversing ? 'required' : 'nullable', 'string', 'min:5', 'max:2000'],
            'amount' => ['prohibited'], 'adjustment_type' => ['prohibited'],
        ]);
    }

    public function attributes(): array
    {
        return [
            'description' => 'سبب العجز أو الزيادة',
            'reason' => 'سبب عكس التسوية',
        ];
    }
}
