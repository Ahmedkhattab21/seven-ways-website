<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\MerchantSettlementPosted;
use App\Models\AccountingPostingLink;
use App\Models\CustomerPayment;
use App\Models\MerchantSettlement;
use App\Models\MerchantSettlementLine;
use App\Models\PaymentMethodAccountMapping;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantSettlementService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private TreasuryOperationAuthorizationService $authorization,
        private MerchantSettlementPostingService $posting,
        private AuditService $audit
    ) {
    }

    public function create(array $data): MerchantSettlement
    {
        return DB::transaction(function () use ($data) {
            if ($data['period_start'] > $data['period_end']) {
                throw new BusinessRuleException('Merchant settlement period is invalid.');
            }
            $prepared = [];
            $gross = '0.0000';
            foreach ($data['lines'] as $line) {
                if ($line['source_type'] !== 'customer_payment') {
                    throw new BusinessRuleException('Only customer payment clearing sources are supported.');
                }
                $source = CustomerPayment::query()->where('company_id', $this->tenant->companyId())
                    ->where('payment_method_id', $data['payment_method_id'])->findOrFail($line['source_id']);
                if (! $this->tenant->user()->canAccessBranch($source->branch)
                    || (! empty($data['branch_id']) && (int) $data['branch_id'] !== $source->branch_id)) {
                    throw new AuthorizationException('Merchant clearing source is outside the settlement branch scope.');
                }
                $mapping = PaymentMethodAccountMapping::query()
                    ->where('company_id', $source->company_id)->where('branch_id', $source->branch_id)
                    ->where('payment_method_id', $source->payment_method_id)->where('operation_type', 'receipt')
                    ->where('is_active', true)->first();
                $posting = AccountingPostingLink::query()->where('company_id', $source->company_id)
                    ->where('source_type', CustomerPayment::class)->where('source_id', $source->id)
                    ->where('posting_action', 'post')->where('status', 'posted')
                    ->whereHas('journalEntry.lines', fn ($q) => $q->where('account_id', $mapping?->clearing_account_id)
                        ->where('debit_amount', '>', 0))->first();
                if (! $mapping?->clearing_account_id || ! $posting) {
                    throw new BusinessRuleException('Merchant settlement requires a posted merchant clearing source.');
                }
                $already = MerchantSettlementLine::query()
                    ->where('source_type', 'customer_payment')->where('source_id', $source->id)
                    ->whereHas('settlement', fn ($q) => $q->where('company_id', $this->tenant->companyId())
                        ->whereNotIn('status', ['cancelled', 'reversed']))
                    ->sum('allocated_amount');
                $remaining = bcsub((string) $source->amount, (string) $already, 4);
                $allocated = number_format((float) $line['allocated_amount'], 4, '.', '');
                if (bccomp($allocated, '0', 4) !== 1 || bccomp($allocated, $remaining, 4) === 1) {
                    throw new BusinessRuleException('Merchant clearing allocation exceeds the remaining source amount.');
                }
                $gross = bcadd($gross, $allocated, 4);
                $prepared[] = [
                    'source_type' => 'customer_payment', 'source_id' => $source->id,
                    'source_reference' => $source->payment_number, 'gross_amount' => $source->amount,
                    'allocated_amount' => $allocated,
                ];
            }
            $this->authorization->assert(
                'treasury.merchant_settlements.create', 'merchant_settlement', 'create',
                (int) $data['currency_id'], $gross, $data['branch_id'] ?? null
            );
            $fees = number_format((float) ($data['fees_amount'] ?? 0), 4, '.', '');
            $tax = number_format((float) ($data['tax_amount'] ?? 0), 4, '.', '');
            $net = bcsub(bcsub($gross, $fees, 4), $tax, 4);
            if (bccomp($net, '0', 4) === -1) {
                throw new BusinessRuleException('Merchant fees and tax cannot exceed gross settlement.');
            }
            $settlement = new MerchantSettlement($data);
            $settlement->forceFill([
                'company_id' => $this->tenant->companyId(),
                'document_number' => $this->numbers->next(
                    'merchant_settlement', $this->tenant->companyId(), $data['branch_id'] ?? null,
                    $data['settlement_date']
                ),
                'gross_amount' => $gross, 'fees_amount' => $fees, 'tax_amount' => $tax,
                'net_amount' => $net, 'status' => 'draft', 'idempotency_key' => (string) Str::uuid(),
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $allocatedFees = '0.0000';
            $allocatedTax = '0.0000';
            foreach (array_values($prepared) as $index => $line) {
                $feesShare = $index === array_key_last($prepared)
                    ? bcsub($fees, $allocatedFees, 4)
                    : bcmul($fees, bcdiv($line['allocated_amount'], $gross, 8), 4);
                $taxShare = $index === array_key_last($prepared)
                    ? bcsub($tax, $allocatedTax, 4)
                    : bcmul($tax, bcdiv($line['allocated_amount'], $gross, 8), 4);
                $allocatedFees = bcadd($allocatedFees, $feesShare, 4);
                $allocatedTax = bcadd($allocatedTax, $taxShare, 4);
                $settlementLine = $settlement->lines()->make([
                    'source_type' => $line['source_type'], 'source_id' => $line['source_id'],
                    'source_reference' => $line['source_reference'],
                ]);
                $settlementLine->forceFill([
                    'gross_amount' => $line['gross_amount'],
                    'allocated_amount' => $line['allocated_amount'],
                    'fees_share' => $feesShare,
                    'net_amount' => bcsub(bcsub($line['allocated_amount'], $feesShare, 4), $taxShare, 4),
                ])->save();
            }
            $this->audit->record('treasury.merchant_settlement.created', $settlement);
            DB::afterCommit(fn () => event(new \App\Events\MerchantSettlementCreated($settlement->id)));

            return $settlement->load('lines');
        });
    }

    public function action(MerchantSettlement $settlement, string $action, ?string $reason = null): MerchantSettlement
    {
        return DB::transaction(function () use ($settlement, $action, $reason) {
            $settlement = MerchantSettlement::query()->where('company_id', $this->tenant->companyId())
                ->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            $ability = in_array($action, ['submit', 'approve', 'post'], true) ? $action : 'post';
            $this->authorization->assert(
                'treasury.merchant_settlements.'.$action, 'merchant_settlement', $ability,
                $settlement->currency_id, (string) $settlement->gross_amount,
                $settlement->branch_id, $settlement->created_by
            );
            if ($action === 'reverse') {
                if ($settlement->status !== 'posted') {
                    throw new BusinessRuleException('Only posted merchant settlements can be reversed.');
                }
                $entry = $this->posting->reverse($settlement, (string) $reason);
                $settlement->forceFill([
                    'status' => 'reversed', 'reversed_by' => $this->tenant->user()->id,
                    'reversal_journal_entry_id' => $entry->id,
                ])->save();
            } else {
                $transitions = [
                    'submit' => ['draft', 'pending_approval', 'submitted_by'],
                    'approve' => ['pending_approval', 'approved', 'approved_by'],
                    'post' => ['approved', 'posted', 'posted_by'],
                    'cancel' => ['draft', 'cancelled', null],
                ];
                if (! isset($transitions[$action])) {
                    throw new BusinessRuleException('Unsupported merchant settlement action.');
                }
                [$from, $to, $actor] = $transitions[$action];
                if ($settlement->status !== $from) {
                    throw new BusinessRuleException('Invalid merchant settlement transition.');
                }
                if ($action === 'approve' && $settlement->created_by === $this->tenant->user()->id) {
                    throw new BusinessRuleException('Settlement creator cannot approve it.');
                }
                $changes = ['status' => $to];
                if ($actor) {
                    $changes[$actor] = $this->tenant->user()->id;
                }
                if ($action === 'post') {
                    $entry = $this->posting->post($settlement);
                    $changes['journal_entry_id'] = $entry->id;
                }
                $settlement->forceFill($changes)->save();
                if ($action === 'post') {
                    DB::afterCommit(fn () => event(new MerchantSettlementPosted($settlement->id)));
                }
            }
            $this->audit->record('treasury.merchant_settlement.'.$action, $settlement);
            $event = match ($action) {
                'approve' => \App\Events\MerchantSettlementApproved::class,
                'reverse' => \App\Events\MerchantSettlementReversed::class,
                default => null,
            };
            if ($event) {
                DB::afterCommit(fn () => event(new $event($settlement->id)));
            }

            return $settlement;
        });
    }
}
