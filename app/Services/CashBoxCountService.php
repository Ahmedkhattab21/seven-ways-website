<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\CashBoxSessionCounted;
use App\Models\AccountingSetting;
use App\Models\CashBoxCount;
use App\Models\CashBoxSession;
use Illuminate\Support\Facades\DB;

class CashBoxCountService
{
    public function __construct(
        private TenantContext $tenant,
        private TreasuryBalanceService $balances,
        private AuditService $audit
    ) {
    }

    public function create(CashBoxSession $session, array $data): CashBoxCount
    {
        return DB::transaction(function () use ($session, $data) {
            $session = CashBoxSession::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($session->cashBox->branch)) {
                throw new BusinessRuleException('Cash session branch is outside the actor scope.');
            }
            if (! in_array($session->status, ['opened', 'counting'], true)) {
                throw new BusinessRuleException('Cash count requires an open counting session.');
            }
            $lines = $data['lines'] ?? [];
            if ($lines === [] && (blank($data['notes'] ?? null) || ! isset($data['counted_total']))) {
                throw new BusinessRuleException('A documented manual total is required when denominations are unavailable.');
            }
            $total = '0.0000';
            foreach ($lines as $line) {
                if (bccomp((string) $line['denomination'], '0', 4) !== 1 || (int) $line['quantity'] < 1) {
                    throw new BusinessRuleException('Cash denomination and quantity must be positive.');
                }
                $total = bcadd($total, bcmul((string) $line['denomination'], (string) $line['quantity'], 4), 4);
            }
            if ($lines === []) {
                $total = number_format((float) $data['counted_total'], 4, '.', '');
            }
            $book = $this->balances->cashBox($session->cashBox)['book_balance'];
            $count = new CashBoxCount([
                'cash_box_session_id' => $session->id, 'count_type' => $data['count_type'],
                'notes' => $data['notes'] ?? null,
            ]);
            $count->forceFill([
                'company_id' => $session->company_id, 'status' => 'draft',
                'counted_total' => $total, 'book_total' => $book,
                'difference' => bcsub($total, $book, 4),
                'counted_by' => $this->tenant->user()->id, 'counted_at' => now(),
            ])->save();
            foreach (array_values($lines) as $index => $line) {
                $countLine = $count->lines()->make([
                    'denomination' => $line['denomination'], 'quantity' => $line['quantity'],
                    'sort_order' => $index + 1,
                ]);
                $countLine->forceFill([
                    'line_total' => bcmul((string) $line['denomination'], (string) $line['quantity'], 4),
                ])->save();
            }
            if ($data['count_type'] === 'opening') {
                $session->forceFill([
                    'opening_counted_balance' => $total,
                    'opening_difference' => bcsub($total, (string) $session->opening_book_balance, 4),
                ])->save();
            }
            $this->audit->record('treasury.cash_session.counted', $count, [
                'count_type' => $count->count_type, 'difference' => $count->difference,
            ]);
            DB::afterCommit(fn () => event(new CashBoxSessionCounted($session->id)));

            return $count->load('lines');
        });
    }

    public function action(CashBoxCount $count, string $action): CashBoxCount
    {
        return DB::transaction(function () use ($count, $action) {
            $count = CashBoxCount::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($count->id)->lockForUpdate()->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($count->session->cashBox->branch)) {
                throw new BusinessRuleException('Cash count branch is outside the actor scope.');
            }
            $transitions = [
                'submit' => ['draft', 'submitted', null, null],
                'review' => ['submitted', 'reviewed', 'reviewed_by', 'reviewed_at'],
                'approve' => ['reviewed', 'approved', 'approved_by', 'approved_at'],
                'cancel' => ['draft', 'cancelled', null, null],
            ];
            if (! isset($transitions[$action])) {
                throw new BusinessRuleException('Unsupported cash count action.');
            }
            [$from, $to, $actor, $time] = $transitions[$action];
            if ($count->status !== $from) {
                throw new BusinessRuleException('Invalid cash count transition.');
            }
            if ($action === 'approve') {
                $settings = AccountingSetting::query()->where('company_id', $count->company_id)->first();
                if ($settings?->separation_of_duties && $count->counted_by === $this->tenant->user()->id) {
                    throw new BusinessRuleException('Cash counter cannot approve the same count.');
                }
            }
            $changes = ['status' => $to];
            if ($actor) {
                $changes += [$actor => $this->tenant->user()->id, $time => now()];
            }
            $count->forceFill($changes)->save();
            $this->audit->record('treasury.cash_count.'.$action, $count);

            return $count;
        });
    }
}
