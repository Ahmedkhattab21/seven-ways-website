<?php

namespace Database\Factories;

use App\Models\WarrantyItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarrantyItemFactory extends Factory
{
    protected $model = WarrantyItem::class;

    public function definition(): array
    {
        return [
            'warranty_id' => 1, 'work_order_service_id' => 1, 'service_id' => 1,
            'warranty_months' => 12, 'start_date' => today(), 'end_date' => today()->addYear(),
            'status' => 'active',
        ];
    }
}
