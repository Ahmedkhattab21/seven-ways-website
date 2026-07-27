<?php

namespace Database\Factories;

use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupplierInvoiceFactory extends Factory
{
    protected $model = SupplierInvoice::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'supplier_invoice_number' => 'EXT-'.$this->faker->unique()->numerify('######'), 'internal_invoice_number' => 'SINV-'.$this->faker->unique()->numerify('######'), 'status' => 'draft', 'invoice_date' => today(), 'subtotal' => 100, 'discount_amount' => 0, 'tax_amount' => 14, 'shipping_amount' => 0, 'other_charges' => 0, 'rounding_amount' => 0, 'total' => 114, 'paid_amount' => 0, 'credited_amount' => 0, 'balance_due' => 114, 'supplier_name_snapshot' => $this->faker->company()];
    }
}
