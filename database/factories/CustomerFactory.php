<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $phone = '010'.fake()->unique()->numerify('########');

        return [
            'company_id' => 1,
            'created_branch_id' => 1,
            'assigned_branch_id' => 1,
            'customer_code' => 'CUS-'.fake()->unique()->numerify('######'),
            'customer_type' => 'individual',
            'name' => fake()->name(),
            'phone' => $phone,
            'normalized_phone' => '20'.substr($phone, 1),
            'preferred_language' => 'ar',
            'credit_limit' => 0,
            'payment_term_days' => 0,
            'status' => 'active',
        ];
    }
}
