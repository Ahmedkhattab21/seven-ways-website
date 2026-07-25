<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('customer') ?? $this->route('vehicle');
        $prefix = $this->route('customer') ? 'customers' : 'vehicles';

        return $target && $this->user()->can('view', $target)
            && $this->user()->hasPermission("{$prefix}.manage_attachments");
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config('attachments.max_kb'),
                'mimes:'.implode(',', config('attachments.extensions')),
                'mimetypes:'.implode(',', config('attachments.mimetypes')),
            ],
            'category' => ['nullable', Rule::in([
                'customer_document', 'commercial_registration', 'tax_certificate',
                'vehicle_photo', 'vehicle_registration', 'insurance', 'other',
            ])],
        ];
    }
}
