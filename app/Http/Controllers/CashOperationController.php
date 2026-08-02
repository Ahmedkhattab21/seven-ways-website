<?php

namespace App\Http\Controllers;

use App\Core\Tenancy\TenantContext;
use App\Http\Requests\CashPaymentActionRequest;
use App\Http\Requests\CashPaymentRequest;
use App\Http\Requests\CashReceiptActionRequest;
use App\Http\Requests\CashReceiptRequest;
use App\Models\CashPayment;
use App\Models\CashReceipt;
use App\Services\CashOperationScopeService;
use App\Services\CashOperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashOperationController extends Controller
{
    public function receipts(TenantContext $tenant, CashOperationScopeService $scope): View
    {
        return $this->index($tenant, $scope, 'receipt');
    }

    public function payments(TenantContext $tenant, CashOperationScopeService $scope): View
    {
        return $this->index($tenant, $scope, 'payment');
    }

    public function options(Request $request, TenantContext $tenant, CashOperationScopeService $scope): JsonResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'in:receipt,payment'],
            'branch_id' => ['required', 'integer'],
        ]);
        $direction = $validated['direction'];
        abort_unless($tenant->user()->hasPermission('treasury.cash_'.$direction.'s.view'), 403);
        $branch = $scope->branch((int) $validated['branch_id']);
        abort_unless($branch, 403);

        return response()->json($this->formOptions($scope, $direction, $branch->id));
    }

    public function storeReceipt(CashReceiptRequest $request, CashOperationService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.cash_receipts.create'), 403);
        $service->create('receipt', $request->validated());

        return back()->with('success', 'تم إنشاء المقبوض كمسودة.');
    }

    public function storePayment(CashPaymentRequest $request, CashOperationService $service): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('treasury.cash_payments.create'), 403);
        $service->create('payment', $request->validated());

        return back()->with('success', 'تم إنشاء المدفوع كمسودة.');
    }

    public function receiptAction(
        CashReceiptActionRequest $request,
        CashReceipt $cashReceipt,
        string $action,
        CashOperationService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cash_receipts.'.$action), 403);
        $service->action($cashReceipt, $action, $request->validated('reason'), $request->validated('date'));

        return back()->with('success', $this->actionMessage('المقبوض', $action));
    }

    public function paymentAction(
        CashPaymentActionRequest $request,
        CashPayment $cashPayment,
        string $action,
        CashOperationService $service
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('treasury.cash_payments.'.$action), 403);
        $service->action($cashPayment, $action, $request->validated('reason'), $request->validated('date'));

        return back()->with('success', $this->actionMessage('المدفوع', $action));
    }

    private function index(
        TenantContext $tenant,
        CashOperationScopeService $scope,
        string $direction
    ): View {
        abort_unless($tenant->user()->hasPermission('treasury.cash_'.$direction.'s.view'), 403);
        $model = $direction === 'receipt' ? CashReceipt::class : CashPayment::class;
        $formBranch = $tenant->branch();

        return view('treasury.cash-operations', [
            'direction' => $direction,
            'operations' => $model::query()->where('company_id', $tenant->companyId())
                ->whereIn('branch_id', $tenant->accessibleBranches()->pluck('id'))->latest('id')->paginate(30),
            'formBranch' => $formBranch,
            'formOptions' => $formBranch
                ? $this->formOptions($scope, $direction, $formBranch->id)
                : ['cash_boxes' => [], 'sessions' => [], 'accounts' => []],
            'branchLocked' => $tenant->user()->hasRole('branch_manager')
                && ! $tenant->user()->isCompanyAdministrator(),
            'company' => $tenant->company(),
            'branches' => $tenant->accessibleBranches(),
        ]);
    }

    private function formOptions(CashOperationScopeService $scope, string $direction, int $branchId): array
    {
        return [
            'cash_boxes' => $scope->cashBoxes($direction, $branchId)->map(fn ($box) => [
                'id' => $box->id,
                'code' => $box->code,
                'name' => $box->name,
                'requires_session' => (bool) $box->requires_shift_opening,
            ])->values(),
            'sessions' => $scope->sessions($branchId)->map(fn ($session) => [
                'id' => $session->id,
                'cash_box_id' => $session->cash_box_id,
                'number' => $session->session_number,
                'cash_box_name' => $session->cashBox->name,
            ])->values(),
            'accounts' => $scope->accounts($direction, $branchId)->map(fn ($account) => [
                'id' => $account->id,
                'code' => $account->account_code,
                'name' => $account->name_ar,
                'classification' => $account->type?->classification,
                'control_type' => $account->control_type,
            ])->values(),
        ];
    }

    private function actionMessage(string $document, string $action): string
    {
        return match ($action) {
            'submit' => "تم إرسال {$document} للمراجعة.",
            'approve' => "تم اعتماد {$document}.",
            'post' => "تم ترحيل {$document}.",
            'reverse' => "تم عكس {$document}.",
            'cancel' => "تم إلغاء {$document}.",
            default => "تم تحديث {$document}.",
        };
    }
}
