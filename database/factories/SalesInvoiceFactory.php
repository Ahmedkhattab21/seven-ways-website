<?php

namespace Database\Factories;

use App\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SalesInvoiceFactory extends Factory
{
    protected $model = SalesInvoice::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'invoice_number' => 'INV-'.$this->faker->unique()->numerify('######'), 'invoice_type' => 'direct_sale', 'status' => 'draft', 'invoice_date' => today(), 'price_includes_tax' => false, 'subtotal' => 100, 'discount_value' => 0, 'discount_amount' => 0, 'tax_amount' => 15, 'rounding_amount' => 0, 'total' => 115, 'paid_amount' => 0, 'credited_amount' => 0, 'refunded_amount' => 0, 'balance_due' => 115, 'customer_name_snapshot' => $this->faker->name()];
    }
}
