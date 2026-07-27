<?php

namespace Database\Factories;

use App\Models\SystemNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SystemNotificationFactory extends Factory
{
    protected $model = SystemNotification::class;

    public function definition(): array
    {
        return [
            'type' => 'approval.requested', 'severity' => 'info',
            'title' => 'Approval required', 'message' => 'A document requires approval.',
            'idempotency_key' => Str::uuid(),
        ];
    }
}
