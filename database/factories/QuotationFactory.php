<?php

namespace Database\Factories;

use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'branch_id' => 1, 'quotation_number' => fake()->unique()->bothify('QT-######'),
            'version_number' => 1, 'customer_id' => 1, 'vehicle_id' => 1, 'status' => 'draft',
            'quotation_date' => today(), 'valid_until' => today()->addDays(7), 'currency_id' => 1,
            'subtotal' => 0, 'discount_value' => 0, 'discount_amount' => 0, 'tax_amount' => 0, 'total' => 0,
            'created_by' => 1,
        ];
    }
}
