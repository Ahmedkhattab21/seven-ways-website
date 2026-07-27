<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\BankStatementImportRequest;
use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Models\BankStatementImportProfile;
use App\Services\BankReconciliationScopeService;
use App\Services\BankStatementImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BankStatementImportController extends Controller
{
    public function index(TenantContext $tenant, BankReconciliationScopeService $scope): View
    {
        abort_unless($tenant->user()->hasPermission('treasury.bank_statements.view'), 403);
        $accountIds = $scope->accountQuery()->where('status', 'active')->pluck('id');

        return view('treasury.bank-statements', [
            'imports' => BankStatementImport::query()->where('company_id', $tenant->companyId())
                ->whereIn('bank_account_id', $accountIds)->with('bankAccount')->latest('id')->paginate(30),
            'accounts' => BankAccount::query()->whereIn('id', $accountIds)->orderBy('account_name')->get(),
            'profiles' => BankStatementImportProfile::query()->where('company_id', $tenant->companyId())
                ->where('is_active', true)->orderByDesc('bank_account_id')->orderBy('name')->get(),
        ]);
    }

    public function store(
        BankStatementImportRequest $request,
        BankStatementImportService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.bank_statements.import'), 403);
        $account = BankAccount::query()->findOrFail($request->integer('bank_account_id'));
        $profile = BankStatementImportProfile::query()->findOrFail($request->integer('profile_id'));
        $service->import($account, $profile, $request->file('file'), $request->safe()->except(['file', 'profile_id', 'bank_account_id']));

        return back()->with('success', 'تم استيراد ملف CSV والتحقق منه بنجاح.');
    }

    public function show(BankStatementImport $bankStatementImport): View
    {
        $this->authorize('view', $bankStatementImport);

        return view('treasury.bank-statement-lines', [
            'import' => $bankStatementImport->load('bankAccount'),
            'lines' => $bankStatementImport->lines()->orderBy('line_number')->paginate(100),
        ]);
    }

    public function download(BankStatementImport $bankStatementImport): StreamedResponse
    {
        $this->authorize('view', $bankStatementImport);
        abort_unless(request()->user()->hasPermission('treasury.bank_statements.view_sensitive'), 403);

        return Storage::disk('local')->download(
            $bankStatementImport->storage_path,
            basename($bankStatementImport->original_file_name),
            ['Content-Type' => 'text/csv', 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
