<?php

namespace Database\Factories;

use App\Models\ScheduledJournalReversal;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledJournalReversalFactory extends Factory
{
    protected $model = ScheduledJournalReversal::class;

    public function definition(): array
    {
        return ['uuid' => $this->faker->uuid(), 'scheduled_date' => now()->addMonth(), 'status' => 'scheduled', 'idempotency_key' => $this->faker->sha256()];
    }
}
