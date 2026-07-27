<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankStatementImportValidationRequest;
use App\Models\BankStatementImport;
use App\Services\BankStatementImportService;
use Illuminate\Http\RedirectResponse;

class BankStatementImportActionController extends Controller
{
    public function __invoke(
        BankStatementImportValidationRequest $request,
        BankStatementImport $bankStatementImport,
        BankStatementImportService $service
    ): RedirectResponse {
        $this->authorize('cancel', $bankStatementImport);
        $service->cancel($bankStatementImport, $request->validated('reason'));

        return back()->with('success', 'تم إلغاء ملف الاستيراد مع الاحتفاظ بالسجل.');
    }
}
