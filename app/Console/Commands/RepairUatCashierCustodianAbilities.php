<?php

namespace App\Console\Commands;

use App\Core\Tenancy\TenantContext;
use App\Models\CashBox;
use App\Models\CashBoxCustodian;
use App\Models\User;
use App\Services\CashBoxCustodianService;
use App\Services\UatEnvironmentGuard;
use Illuminate\Console\Command;
use RuntimeException;

class RepairUatCashierCustodianAbilities extends Command
{
    protected $signature = 'uat:repair-cashier-custodian-abilities';

    protected $description = 'Repair the active UAT cashier cash-box abilities';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('STOP — This command is not allowed in production.');

            return self::FAILURE;
        }
        app(UatEnvironmentGuard::class)->assertSafe();

        $user = User::query()->where('email', 'cashier@sevenways.test')->where('status', 'active')->first();
        if (! $user) {
            throw new RuntimeException('Active cashier@sevenways.test was not found.');
        }
        $box = CashBox::query()->where('company_id', $user->company_id)->where('status', 'active')
            ->where(function ($query): void {
                $query->whereIn('code', ['UAT-CAI-CASH', 'CAI-MAIN-CASH', 'MAIN-CAI-MAIN'])
                    ->orWhere('name', 'الخزينة الرئيسية - القاهرة')
                    ->orWhere('name', 'like', '%القاهرة%');
            })->first();
        if (! $box) {
            throw new RuntimeException('The Cairo main cash box was not found.');
        }
        $custodian = CashBoxCustodian::query()->where('company_id', $user->company_id)
            ->where('cash_box_id', $box->id)->where('user_id', $user->id)->where('is_active', true)
            ->first();
        if (! $custodian) {
            throw new RuntimeException('An active cashier custodian assignment was not found; no duplicate was created.');
        }

        app(TenantContext::class)->initialize(
            User::query()->where('company_id', $user->company_id)->where('status', 'active')
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['company_owner', 'system_admin']))->firstOrFail()
        );
        $target = [
            'can_receive' => true, 'can_pay' => true, 'can_transfer' => true,
            'payment_limit' => 10000.0, 'is_primary' => true, 'valid_to' => $custodian->valid_to?->toDateString(),
        ];
        $current = [
            'can_receive' => (bool) $custodian->can_receive, 'can_pay' => (bool) $custodian->can_pay,
            'can_transfer' => (bool) $custodian->can_transfer, 'payment_limit' => (float) $custodian->payment_limit,
            'is_primary' => (bool) $custodian->is_primary, 'valid_to' => $custodian->valid_to?->toDateString(),
        ];
        if ($current === $target) {
            $this->info("{$custodian->id} already has the requested abilities; no changes made.");

            return self::SUCCESS;
        }
        $updated = app(CashBoxCustodianService::class)->update($custodian, $target);
        $this->info(sprintf(
            '%s repaired: receive=%s pay=%s transfer=%s limit=%s primary=%s',
            $updated->id, $updated->can_receive ? 'yes' : 'no', $updated->can_pay ? 'yes' : 'no',
            $updated->can_transfer ? 'yes' : 'no', $updated->payment_limit, $updated->is_primary ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }
}
