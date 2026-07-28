<?php

namespace App\Console\Commands;

use App\Core\Tenancy\TenantContext;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\User;
use App\Services\BankAccountAccessService;
use Illuminate\Console\Command;

class RepairUatBankAccess extends Command
{
    protected $signature = 'uat:repair-bank-access {account_code}';

    protected $description = 'Repair the default branch access for an empty UAT bank account';

    public function handle(TenantContext $tenant, BankAccountAccessService $accessService): int
    {
        if (app()->environment('production')) {
            $this->error('STOP — This command is not allowed in production.');

            return self::FAILURE;
        }

        $account = BankAccount::query()->where('account_code', $this->argument('account_code'))
            ->where('status', 'active')->with('company')->first();
        $branch = Branch::query()->where('company_id', $account?->company_id)
            ->where('code', 'CAI-MAIN')->first();
        if (! $account || $account->company?->name !== 'Seven Ways' || ! $branch
            || $account->branch_id !== $branch->id) {
            $this->error('STOP — Account, company, or CAI-MAIN branch verification failed.');

            return self::FAILURE;
        }

        $actor = User::query()->where('company_id', $account->company_id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'company_owner'))
            ->where('status', 'active')->first();
        if (! $actor) {
            $this->error('STOP — No active company owner is available.');

            return self::FAILURE;
        }

        $tenant->initialize($actor);
        $accessService->save($account, [
            'branch_id' => $branch->id, 'is_active' => true,
            'can_view' => true, 'can_receive' => true,
            'can_pay' => false, 'can_transfer' => false,
        ]);

        $this->info("READY — {$account->account_code} has valid receipt access for {$branch->code}.");

        return self::SUCCESS;
    }
}
