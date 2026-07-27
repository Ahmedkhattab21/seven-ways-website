<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AuditEventFactory extends Factory
{
    protected $model = AuditEvent::class;

    public function definition(): array
    {
        return [
            'event_type' => 'approval.requested', 'module' => 'approvals',
            'action' => 'request', 'correlation_id' => Str::uuid(),
            'occurred_at' => now(), 'created_at' => now(),
        ];
    }
}
