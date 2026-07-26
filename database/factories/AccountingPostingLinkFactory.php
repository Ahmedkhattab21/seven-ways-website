<?php

namespace Database\Factories;

use App\Models\AccountingPostingLink;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountingPostingLinkFactory extends Factory
{
    protected $model = AccountingPostingLink::class;

    public function definition(): array
    {
        return [
            'source_type' => 'test', 'source_id' => fake()->unique()->numberBetween(1, 1000000),
            'posting_action' => 'post', 'idempotency_key' => fake()->unique()->sha256(),
            'status' => 'posted',
        ];
    }
}
