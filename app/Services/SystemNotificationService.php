<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SystemNotificationService
{
    private const ALLOWED_METADATA = ['module', 'document_number', 'status', 'priority', 'due_at'];

    public function send(
        User $recipient,
        string $type,
        string $title,
        string $message,
        string $idempotencyKey,
        ?Model $related = null,
        array $options = []
    ): SystemNotification {
        $notification = SystemNotification::where('idempotency_key', $idempotencyKey)->first() ?? new SystemNotification;
        if ($notification->exists) {
            return $notification;
        }
        $notification->forceFill([
            'idempotency_key' => $idempotencyKey,
            'company_id' => $recipient->company_id,
            'branch_id' => $options['branch_id'] ?? null,
            'user_id' => $recipient->id,
            'type' => $type,
            'severity' => $options['severity'] ?? 'info',
            'title' => Str::limit(strip_tags($title), 200, ''),
            'message' => Str::limit(strip_tags($message), 500, ''),
            'action_url' => $this->safeUrl($options['action_url'] ?? null),
            'metadata' => Arr::only($options['metadata'] ?? [], self::ALLOWED_METADATA),
        ]);
        if ($related) {
            $notification->related()->associate($related);
        }
        $notification->save();

        return $notification;
    }

    private function safeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (parse_url($url, PHP_URL_HOST) !== null || ! str_starts_with($url, '/')) {
            return null;
        }
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && Str::startsWith($path, '/') ? $path : null;
    }
}
