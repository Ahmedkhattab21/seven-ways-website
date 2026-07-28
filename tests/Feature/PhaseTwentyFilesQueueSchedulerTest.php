<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Models\Attachment;
use App\Services\AttachmentService;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class PhaseTwentyFilesQueueSchedulerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_private_attachment_path_traversal_is_blocked(): void
    {
        $tenant = Mockery::mock(TenantContext::class);
        $tenant->shouldReceive('companyId')->andReturn(10);
        $audit = Mockery::mock(AuditService::class);
        $service = new AttachmentService($tenant, $audit);
        $attachment = new Attachment([
            'disk' => 'local',
            'path' => 'private/attachments/10/../../.env',
            'original_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->expectException(NotFoundHttpException::class);
        $service->download($attachment);
    }

    public function test_upload_allowlist_excludes_executable_and_svg_files(): void
    {
        $this->assertEqualsCanonicalizing(
            ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
            config('attachments.extensions')
        );
        foreach (['php', 'phar', 'exe', 'sh', 'svg', 'html'] as $extension) {
            $this->assertNotContains($extension, config('attachments.extensions'));
        }
    }

    public function test_database_queue_and_failed_job_tables_are_ready(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertStringContainsString('QUEUE_CONNECTION=database', file_get_contents(base_path('.env.example')));
    }

    public function test_scheduler_lists_all_idempotent_operational_commands(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        foreach ([
            'quotations:expire',
            'invoices:mark-overdue',
            'supplier-invoices:mark-overdue',
            'approvals:mark-overdue',
            'delegations:expire',
            'notifications:generate-operational',
        ] as $command) {
            $this->assertStringContainsString($command, $output);
        }
    }
}
