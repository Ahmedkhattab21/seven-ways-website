<?php

namespace Tests\Feature;

use App\Models\SystemNotification;
use App\Services\SystemNotificationService;
use App\Services\UnifiedAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class PhaseEighteenNotificationsAuditTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_notification_is_idempotent_scoped_and_external_url_is_rejected(): void
    {
        $context = $this->treasuryContext();
        $service = app(SystemNotificationService::class);
        $first = $service->send($context['user'], 'approval.requested', 'Title', 'Message', 'same-event', null, [
            'action_url' => 'https://evil.example/phish', 'metadata' => ['token' => 'secret', 'module' => 'purchasing'],
        ]);
        $second = $service->send($context['user'], 'approval.requested', 'Again', 'Again', 'same-event');

        $this->assertSame($first->id, $second->id);
        $this->assertNull($first->action_url);
        $this->assertSame(['module' => 'purchasing'], $first->metadata);
        $this->assertSame(1, SystemNotification::where('idempotency_key', 'same-event')->count());
    }

    public function test_notification_read_actions_cannot_touch_another_user(): void
    {
        $context = $this->treasuryContext();
        $permission = \App\Models\Permission::updateOrCreate(
            ['name' => 'notifications.view'], ['display_name' => 'View notifications']
        );
        $context['user']->roles()->first()->permissions()->syncWithoutDetaching([$permission->id]);
        $notification = app(SystemNotificationService::class)->send(
            $context['approver'], 'approval.requested', 'Title', 'Message', 'other-user'
        );

        $this->post(route('notifications.read', $notification))->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_audit_masks_sensitive_values_has_correlation_and_is_immutable(): void
    {
        $this->treasuryContext();
        $audit = app(UnifiedAuditService::class)->record('security.test', 'security', 'view', null, [
            'new_values' => ['password' => 'plain', 'token' => 'abc', 'status' => 'active'],
        ]);
        $this->assertSame('[MASKED]', $audit->new_values['password']);
        $this->assertSame('[MASKED]', $audit->new_values['token']);
        $this->assertSame('active', $audit->new_values['status']);
        $this->assertNotEmpty($audit->correlation_id);

        $this->expectException(LogicException::class);
        $audit->forceFill(['action' => 'forged'])->save();
    }

    public function test_correlation_id_is_returned_without_breaking_response(): void
    {
        $response = $this->get('/api/health');
        $response->assertOk()->assertHeader('X-Correlation-ID');
    }
}
