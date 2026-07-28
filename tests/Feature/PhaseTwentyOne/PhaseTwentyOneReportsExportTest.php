<?php

namespace Tests\Feature\PhaseTwentyOne;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\UsesPhaseTwentyOneUat;
use Tests\TestCase;

class PhaseTwentyOneReportsExportTest extends TestCase
{
    use DatabaseTransactions;
    use UsesPhaseTwentyOneUat;

    public function test_uat_owner_can_render_scoped_dashboards_and_reports(): void
    {
        $this->setUpUatContext();

        $this->assertTrue(auth()->user()->hasPermission('dashboard.view'));
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('dashboards.executive'))->assertOk();
        $this->get(route('dashboards.branches'))->assertOk();
        $this->get(route('approvals.index'))->assertOk();
        $this->get(route('notifications.index'))->assertOk();
        $this->get(route('audit.index'))->assertOk();
    }

    public function test_viewer_has_no_export_or_sensitive_report_permission(): void
    {
        $viewer = $this->setUpUatContext('uat.viewer@sevenways.test');

        $this->assertFalse($viewer->hasPermission('analytics.exports.export'));
        $this->assertFalse($viewer->hasPermission('accounting.accounts.view_sensitive'));
        $this->assertFalse($viewer->hasPermission('inventory.view_cost'));
    }
}
