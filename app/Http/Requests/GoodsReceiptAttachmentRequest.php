<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoodsReceiptAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->route('goodsReceipt');

        return $receipt && $this->user()->can('inspect', $receipt);
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
            'category' => ['required', Rule::in([
                'goods_receipt_inspection',
                'goods_receipt_damage',
                'supplier_delivery_note',
            ])],
        ];
    }
}
