<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\BankReconciliationSessionRequest;
use App\Models\BankAdjustment;
use App\Models\BankReconciliationSession;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Services\AuditService;
use App\Services\BankBookTransactionService;
use App\Services\BankReconciliationCalculationService;
use App\Services\BankReconciliationScopeService;
use App\Services\BankReconciliationSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankReconciliationController extends Controller
{
    public function index(TenantContext $tenant, BankReconciliationScopeService $scope): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.reconciliation.view'), 403);

        return view('treasury.reconciliations', [
            'sessions' => BankReconciliationSession::query()->where('company_id', $tenant->companyId())
                ->whereIn('bank_account_id', $scope->accountIds())
                ->with('bankAccount')->latest('id')->paginate(30),
            'accounts' => $scope->accountQuery()->where('status', 'active')->orderBy('account_name')->get(),
            'imports' => BankStatementImport::query()->where('company_id', $tenant->companyId())
                ->whereIn('bank_account_id', $scope->accountIds())
                ->where('status', 'imported')->orderByDesc('period_end')->get(),
        ]);
    }

    public function store(
        BankReconciliationSessionRequest $request,
        BankReconciliationSessionService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.reconciliation.create'), 403);
        $session = $service->create($request->validated());

        return redirect()->route('treasury.reconciliations.show', $session)
            ->with('success', 'تم إنشاء جلسة المطابقة.');
    }

    public function show(
        BankReconciliationSession $bankReconciliationSession,
        BankBookTransactionService $book,
        BankReconciliationCalculationService $calculation
    ): View {
        $this->authorize('view', $bankReconciliationSession);
        $bankReconciliationSession->load(['bankAccount', 'imports', 'matches.items', 'adjustments']);
        $statementLines = BankStatementLine::query()
            ->whereIn('bank_statement_import_id', $bankReconciliationSession->imports->pluck('id'))
            ->orderBy('transaction_date')->orderBy('line_number')->get();

        return view('treasury.reconciliation-show', [
            'session' => $bankReconciliationSession, 'statementLines' => $statementLines,
            'bookLines' => $book->transactions(
                $bankReconciliationSession->bankAccount,
                $bankReconciliationSession->date_from->toDateString(),
                $bankReconciliationSession->date_to->toDateString()
            ),
            'totals' => $calculation->calculate($bankReconciliationSession),
        ]);
    }

    public function reports(
        TenantContext $tenant,
        BankReconciliationScopeService $scope,
        BankBookTransactionService $book
    ): View {
        abort_unless($tenant->user()->hasPermission('treasury.reconciliation.view'), 403);
        $sessions = BankReconciliationSession::query()->where('company_id', $tenant->companyId())
            ->whereIn('bank_account_id', $scope->accountIds())
            ->with('bankAccount')->latest('date_to')->limit(100)->get();
        $unmatchedBook = $sessions->groupBy('bank_account_id')->map->first()->flatMap(
            fn ($session) => $book->transactions(
                $session->bankAccount, $session->date_from->toDateString(), $session->date_to->toDateString()
            )->filter(fn ($line) => bccomp((string) $line->reconciliation_unmatched_amount, '0', 4) === 1)
        )->take(200);

        return view('treasury.reconciliation-reports', [
            'unmatchedLines' => BankStatementLine::query()->where('company_id', $tenant->companyId())
                ->whereIn('bank_account_id', $scope->accountIds())
                ->whereIn('status', ['unmatched', 'partially_matched'])->latest('transaction_date')->limit(200)->get(),
            'duplicateLines' => BankStatementLine::query()->where('company_id', $tenant->companyId())
                ->whereIn('bank_account_id', $scope->accountIds())
                ->where('is_duplicate', true)->latest('transaction_date')->limit(200)->get(),
            'sessions' => $sessions, 'unmatchedBook' => $unmatchedBook,
            'adjustments' => BankAdjustment::query()->where('company_id', $tenant->companyId())
                ->whereIn('bank_account_id', $scope->accountIds())->latest('adjustment_date')->limit(200)->get(),
        ]);
    }

    public function export(
        BankReconciliationSession $bankReconciliationSession,
        AuditService $audit
    ): StreamedResponse {
        $this->authorize('view', $bankReconciliationSession);
        abort_unless(request()->user()->hasPermission('treasury.reconciliation.export'), 403);
        $bankReconciliationSession->load('imports');
        $lines = BankStatementLine::query()
            ->whereIn('bank_statement_import_id', $bankReconciliationSession->imports->pluck('id'))
            ->orderBy('transaction_date')->get();
        $audit->record('bank_reconciliation.exported', $bankReconciliationSession, ['line_count' => $lines->count()]);

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Reference', 'Description', 'Debit', 'Credit', 'Matched', 'Remaining', 'Status']);
            foreach ($lines as $line) {
                fputcsv($out, array_map([$this, 'safeCsv'], [
                    $line->transaction_date->toDateString(), $line->bank_reference, $line->description,
                    $line->debit_amount, $line->credit_amount, $line->matched_amount, $line->unmatched_amount, $line->status,
                ]));
            }
            fclose($out);
        }, 'reconciliation-'.$bankReconciliationSession->session_number.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function safeCsv(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
