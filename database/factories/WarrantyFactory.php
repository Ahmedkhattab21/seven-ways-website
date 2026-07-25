<?php

namespace Database\Factories;

use App\Models\Warranty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WarrantyFactory extends Factory
{
    protected $model = Warranty::class;

    public function definition(): array
    {
        return [
            'company_id' => 1, 'branch_id' => 1, 'warranty_number' => fake()->unique()->bothify('WAR-######'),
            'customer_id' => 1, 'vehicle_id' => 1, 'work_order_id' => 1, 'status' => 'active',
            'start_date' => today(), 'end_date' => today()->addYear(), 'terms_snapshot' => [],
            'qr_token' => Str::random(64), 'issued_at' => now(), 'issued_by' => 1,
        ];
    }
}
