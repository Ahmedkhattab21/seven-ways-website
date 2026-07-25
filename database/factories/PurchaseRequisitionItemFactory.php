<?php

namespace Database\Factories;

use App\Models\PurchaseRequisitionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseRequisitionItemFactory extends Factory
{
    protected $model = PurchaseRequisitionItem::class;

    public function definition(): array
    {
        return ['requested_quantity' => 10, 'approved_quantity' => null, 'status' => 'pending', 'ordered_quantity' => 0];
    }
}
