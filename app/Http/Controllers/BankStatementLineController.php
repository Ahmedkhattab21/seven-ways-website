<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankStatementLineActionRequest;
use App\Models\BankStatementLine;
use App\Services\BankStatementLineService;
use Illuminate\Http\RedirectResponse;

class BankStatementLineController extends Controller
{
    public function action(
        BankStatementLineActionRequest $request,
        BankStatementLine $bankStatementLine,
        string $action,
        BankStatementLineService $service
    ): RedirectResponse {
        if ($action === 'ignore') {
            $this->authorize('ignore', $bankStatementLine);
            $service->ignore($bankStatementLine, $request->validated('reason'));
        } elseif ($action === 'duplicate') {
            $this->authorize('resolveDuplicate', $bankStatementLine);
            $duplicate = $request->filled('duplicate_of_id')
                ? BankStatementLine::query()->findOrFail($request->integer('duplicate_of_id')) : null;
            $service->classifyDuplicate($bankStatementLine, $duplicate, $request->validated('reason'));
        } else {
            abort(404);
        }

        return back()->with('success', 'تم تحديث تصنيف سطر كشف الحساب مع تسجيل السبب.');
    }
}
