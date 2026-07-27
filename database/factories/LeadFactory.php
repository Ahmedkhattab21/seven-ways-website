<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        $phone = '010'.fake()->unique()->numerify('########');

        return [
            'company_id' => 1,
            'branch_id' => 1,
            'lead_number' => 'LEAD-'.fake()->unique()->numerify('######'),
            'name' => fake()->name(),
            'phone' => $phone,
            'normalized_phone' => '20'.substr($phone, 1),
            'status' => 'new',
            'priority' => 'normal',
            'created_by' => 1,
        ];
    }
}
