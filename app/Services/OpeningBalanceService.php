<?php

namespace App\Services;

use App\Core\Exceptions\BusinessRuleException;
use App\Core\Tenancy\TenantContext;
use App\Events\OpeningBalanceApproved;
use App\Events\OpeningBalanceCreated;
use App\Events\OpeningBalanceReadyForPosting;
use App\Events\OpeningBalanceSubmitted;
use App\Models\Branch;
use App\Models\FiscalYear;
use App\Models\OpeningBalanceDocument;
use Illuminate\Support\Facades\DB;

class OpeningBalanceService
{
    public function __construct(
        private TenantContext $tenant,
        private DocumentNumberService $numbers,
        private OpeningBalanceValidationService $validator,
        private AuditService $audit
    ) {
    }

    public function create(array $data): OpeningBalanceDocument
    {
        $year = FiscalYear::query()->whereKey($data['fiscal_year_id'])
            ->where('company_id', $this->tenant->companyId())->firstOrFail();
        if ($data['balance_date'] < $year->start_date->toDateString()
            || $data['balance_date'] > $year->end_date->toDateString()) {
            throw new BusinessRuleException('Opening balance date must be inside the fiscal year.');
        }
        if (! empty($data['branch_id'])) {
            $branch = Branch::query()->whereKey($data['branch_id'])
                ->where('company_id', $this->tenant->companyId())->firstOrFail();
            if (! $this->tenant->user()->canAccessBranch($branch)) {
                throw new BusinessRuleException('Branch is outside the accessible scope.');
            }
        }

        return DB::transaction(function () use ($data) {
            $document = new OpeningBalanceDocument($data);
            $document->forceFill([
                'company_id' => $this->tenant->companyId(),
                'document_number' => $this->numbers->next(
                    'opening_balance', $this->tenant->companyId(), $data['branch_id'] ?? null, $data['balance_date']
                ),
                'status' => 'draft', 'total_debit' => 0, 'total_credit' => 0,
                'created_by' => $this->tenant->user()->id,
            ])->save();
            $this->audit->record('opening_balance.created', $document);
            DB::afterCommit(fn () => event(new OpeningBalanceCreated($document->id)));

            return $document;
        });
    }

    public function addLine(OpeningBalanceDocument $document, array $data): void
    {
        $this->assertEditable($document);
        DB::transaction(function () use ($document, $data) {
            $document = OpeningBalanceDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $this->validator->validateLine($document, $data);
            $document->lines()->create($data);
            $this->rebuildTotals($document);
        });
    }

    public function action(OpeningBalanceDocument $document, string $action): OpeningBalanceDocument
    {
        return DB::transaction(function () use ($document, $action) {
            $document = OpeningBalanceDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            if ($document->company_id !== $this->tenant->companyId()) {
                throw new BusinessRuleException('Opening balance is outside the current company.');
            }
            $map = [
                'submit' => ['draft', 'pending_approval', 'submitted_by', OpeningBalanceSubmitted::class],
                'approve' => ['pending_approval', 'approved', 'approved_by', OpeningBalanceApproved::class],
                'mark_ready' => ['approved', 'ready_for_posting', null, OpeningBalanceReadyForPosting::class],
            ];
            if (! isset($map[$action]) || $document->status !== $map[$action][0]) {
                throw new BusinessRuleException('Invalid opening balance transition.');
            }
            $this->rebuildTotals($document);
            $this->validator->assertBalanced($document);
            $settings = \App\Models\AccountingSetting::query()->where('company_id', $document->company_id)->first();
            if ($action === 'approve' && $settings?->separation_of_duties
                && $document->created_by === $this->tenant->user()->id) {
                throw new BusinessRuleException('Separation of duties prevents approving your own document.');
            }
            $changes = ['status' => $map[$action][1]];
            if ($map[$action][2]) {
                $changes[$map[$action][2]] = $this->tenant->user()->id;
            }
            $document->forceFill($changes)->save();
            $this->audit->record('opening_balance.'.$action, $document);
            $event = $map[$action][3];
            DB::afterCommit(fn () => event(new $event($document->id)));

            return $document;
        });
    }

    private function rebuildTotals(OpeningBalanceDocument $document): void
    {
        $document->forceFill([
            'total_debit' => $document->lines()->sum('debit_amount'),
            'total_credit' => $document->lines()->sum('credit_amount'),
        ])->save();
    }

    private function assertEditable(OpeningBalanceDocument $document): void
    {
        if ($document->company_id !== $this->tenant->companyId() || $document->status !== 'draft') {
            throw new BusinessRuleException('Only a draft opening balance can be edited.');
        }
    }
}
