<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesInvoiceActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => [$this->route('action') === 'void' ? 'required' : 'nullable', 'string', 'max:2000']];
    }
}
