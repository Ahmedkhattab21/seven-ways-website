<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GoodsReceiptFactory extends Factory
{
    protected $model = GoodsReceipt::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'goods_receipt_number' => 'GR-'.$this->faker->unique()->numerify('######'), 'status' => 'draft', 'receipt_date' => today()];
    }
}
