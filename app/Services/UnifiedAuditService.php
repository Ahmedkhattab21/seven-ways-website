<?php

namespace App\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnifiedAuditService
{
    private const SENSITIVE = [
        'password', 'password_confirmation', 'token', 'api_token', 'secret', 'account_number',
        'iban', 'cheque_number', 'card_number', 'attachment_path',
    ];

    public function __construct(private TenantContext $tenant)
    {
    }

    public function record(string $event, string $module, string $action, ?Model $model = null, array $context = []): AuditEvent
    {
        $audit = new AuditEvent;
        $audit->forceFill([
            'company_id' => $context['company_id'] ?? $this->tenant->companyId(),
            'branch_id' => $context['branch_id'] ?? $this->tenant->branchId(),
            'user_id' => $context['user_id'] ?? $this->tenant->user()?->id,
            'effective_actor_id' => $context['effective_actor_id'] ?? $this->tenant->user()?->id,
            'delegated_by' => $context['delegated_by'] ?? null,
            'event_type' => $event,
            'module' => $module,
            'action' => $action,
            'document_number' => $context['document_number'] ?? null,
            'old_values' => $this->mask($context['old_values'] ?? []),
            'new_values' => $this->mask($context['new_values'] ?? []),
            'changed_fields' => array_values($context['changed_fields'] ?? []),
            'reason' => isset($context['reason']) ? Str::limit(strip_tags((string) $context['reason']), 500, '') : null,
            'ip_address' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 255, ''),
            'correlation_id' => (string) (request()?->attributes->get('correlation_id') ?: Str::uuid()),
            'occurred_at' => now(),
            'created_at' => now(),
        ]);
        if ($model) {
            $audit->auditable()->associate($model);
        }
        $audit->save();

        return $audit;
    }

    private function mask(array $values): array
    {
        return collect($values)->mapWithKeys(function ($value, $key) {
            $sensitive = in_array(Str::lower((string) $key), self::SENSITIVE, true);

            return [$key => $sensitive ? '[MASKED]' : (is_array($value) ? $this->mask($value) : $value)];
        })->all();
    }
}
