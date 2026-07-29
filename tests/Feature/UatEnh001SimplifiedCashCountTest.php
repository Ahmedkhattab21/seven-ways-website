<?php

namespace Tests\Feature;

use App\Core\Exceptions\BusinessRuleException;
use App\Models\CashBox;
use App\Services\CashBoxCountService;
use App\Services\CashBoxCustodianService;
use App\Services\CashBoxSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsTreasuryOperationsContext;
use Tests\TestCase;

class UatEnh001SimplifiedCashCountTest extends TestCase
{
    use BuildsTreasuryOperationsContext;
    use DatabaseTransactions;

    public function test_match_manual_and_empty_modes_are_server_calculated_without_lines(): void
    {
        $context = $this->treasuryContext();
        $box = CashBox::query()->where('company_id', $context['company']->id)
            ->where('branch_id', $context['branch']->id)->firstOrFail();
        app(CashBoxCustodianService::class)->assign($box, [
            'user_id' => $context['cashier']->id, 'valid_from' => '2020-01-01',
            'can_receive' => true, 'can_pay' => true, 'can_transfer' => true, 'is_primary' => true,
        ]);
        $this->switchTreasuryActor($context['cashier']);
        $session = app(CashBoxSessionService::class)->open([
            'cash_box_id' => $box->id, 'custodian_user_id' => $context['cashier']->id,
            'business_date' => '2040-01-10',
        ]);
        $service = app(CashBoxCountService::class);
        $match = $service->create($session, ['count_type' => 'opening', 'count_input_mode' => 'match_book']);
        $manual = $service->create($session, [
            'count_type' => 'interim', 'count_input_mode' => 'manual_total', 'counted_total' => 700,
        ]);
        $empty = $service->create($session, ['count_type' => 'closing', 'count_input_mode' => 'empty']);
        $this->assertSame('0.0000', $match->difference);
        $this->assertSame('700.0000', $manual->difference);
        $this->assertSame('0.0000', $empty->difference);
        $this->assertCount(0, $match->lines);
        $this->assertCount(0, $manual->lines);
        $this->assertCount(0, $empty->lines);
        $this->expectException(BusinessRuleException::class);
        $service->create($session, [
            'count_type' => 'interim', 'count_input_mode' => 'manual_total', 'counted_total' => 0,
        ]);
    }
}
