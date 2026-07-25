<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('company'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'commercial_registration' => ['nullable', 'string', 'max:255', Rule::unique('companies')->ignore($this->route('company'))],
            'tax_number' => ['nullable', 'string', 'max:255', Rule::unique('companies')->ignore($this->route('company'))],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'country_code' => ['required', 'string', 'size:2'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'timezone' => ['required', 'timezone'],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'date_format' => ['required', Rule::in(['Y-m-d', 'd/m/Y', 'd-m-Y'])],
            'time_format' => ['required', Rule::in(['H:i', 'h:i A'])],
            'money_decimal_places' => ['required', 'integer', 'between:0,4'],
            'default_language' => ['required', Rule::in(['ar', 'en'])],
            'ui_direction' => ['required', Rule::in(['rtl', 'ltr'])],
            'default_tax_id' => [
                'nullable',
                'integer',
                Rule::exists('taxes', 'id')->where(
                    fn (Builder $query) => $query
                        ->where('company_id', $this->route('company')->id)
                        ->where('is_active', true)
                ),
            ],
            'is_active' => ['nullable', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }
}
