<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AccountingClosingActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $runAction = in_array($this->route('action'), ['review', 'approve', 'execute'], true)
            || $this->routeIs('accounting.closing.year-end.reopen.approve');

        return [
            'company_id' => ['prohibited'], 'status' => ['prohibited'], 'actors' => ['prohibited'],
            'run_number' => ['prohibited'], 'validation_snapshot' => ['prohibited'],
            'reason' => [$runAction ? 'nullable' : 'required', 'string', 'min:5', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'closing_type' => ['nullable', 'in:period_soft_close,period_hard_close'],
        ];
    }
}
