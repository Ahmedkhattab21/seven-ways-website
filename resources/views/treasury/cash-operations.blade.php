@extends('layouts.app')
@php
    $plural = $direction === 'receipt' ? 'cash-receipts' : 'cash-payments';
    $permissionBase = str_replace('-', '_', $plural);
    $documentLabel = $direction === 'receipt' ? 'المقبوض' : 'المدفوع';
    $statusLabels = [
        'draft' => 'مسودة',
        'pending_approval' => 'مُرسل للمراجعة',
        'approved' => 'معتمد',
        'posted' => 'مُرحّل',
        'reversed' => 'معكوس',
        'cancelled' => 'ملغي',
    ];
    $actions = [
        'draft' => ['submit', 'إرسال للمراجعة'],
        'pending_approval' => ['approve', 'اعتماد'],
        'approved' => ['post', 'ترحيل'],
        'posted' => ['reverse', 'عكس'],
    ];
@endphp
@section('title', $direction === 'receipt' ? 'المقبوضات النقدية' : 'المدفوعات النقدية')
@section('page-title', $direction === 'receipt' ? 'المقبوضات النقدية العامة' : 'المدفوعات النقدية العامة')
@section('content')
<div class="cash-operations-page">
@if(collect($formOptions['sessions'])->isEmpty())<div class="sw-alert sw-alert--warning">لا توجد جلسة خزينة جاهزة للتشغيل. أكمل العد الافتتاحي واعتماده أولًا.</div>@endif
<div class="sw-card cash-operation-notice"><p>لا تستخدم هذه الشاشة بدل تحصيلات العملاء أو مدفوعات الموردين المرتبطة بالفواتير.</p></div>
@if(auth()->user()->hasPermission('treasury.'.$permissionBase.'.create'))
<form
    class="sw-card cash-operation-form"
    method="POST"
    action="{{ route('treasury.'.$plural.'.store') }}"
    data-cash-operation-form
    data-direction="{{ $direction }}"
    data-options-url="{{ route('treasury.cash-operations.options') }}"
>@csrf
    <div class="sw-form-grid">
        <label class="sw-field">
            <span class="sw-field__label">الفرع</span>
            @if($branchLocked)
                <input type="hidden" name="branch_id" value="{{ $formBranch?->id }}" data-cash-branch>
                <input class="sw-input" value="{{ $formBranch?->name }}" disabled>
            @else
                <select class="sw-input" name="branch_id" required data-cash-branch>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branch->id === $formBranch?->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
        </label>
        <label class="sw-field">
            <span class="sw-field__label">الخزينة</span>
            <select class="sw-input" name="cash_box_id" required data-cash-box>
                <option value="">اختر الخزينة</option>
                @foreach($formOptions['cash_boxes'] as $box)
                    <option value="{{ $box['id'] }}" data-requires-session="{{ $box['requires_session'] ? '1' : '0' }}">{{ $box['code'] }} — {{ $box['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="sw-field">
            <span class="sw-field__label">جلسة الخزينة</span>
            <select class="sw-input" name="cash_box_session_id" data-cash-session>
                <option value="">اختر الخزينة أولًا</option>
            </select>
        </label>
        @if($direction === 'receipt')
            <label class="sw-field"><span class="sw-field__label">نوع المقبوض</span><select class="sw-input" name="receipt_type"><option value="other_income">إيراد آخر</option><option value="employee_return">رد موظف</option><option value="supplier_refund">رد مورد</option><option value="capital_injection">إضافة رأس مال</option><option value="miscellaneous">متنوع</option></select></label>
        @else
            <label class="sw-field"><span class="sw-field__label">نوع المدفوع</span><select class="sw-input" name="payment_type"><option value="general_expense">مصروف عام</option><option value="employee_advance">سلفة موظف</option><option value="employee_reimbursement">تعويض موظف</option><option value="petty_cash">عهدة نقدية</option><option value="miscellaneous">متنوع</option></select></label>
        @endif
        <label class="sw-field"><span class="sw-field__label">تاريخ المستند</span><input class="sw-input" name="document_date" type="date" value="{{ now()->toDateString() }}" required></label>
        <input type="hidden" name="currency_id" value="{{ $company->currency_id }}"><input type="hidden" name="exchange_rate" value="1">
        <label class="sw-field"><span class="sw-field__label">المبلغ</span><input class="sw-input" name="amount" type="number" step="0.01" min="0.01" required></label>
        <label class="sw-field">
            <span class="sw-field__label">الحساب المقابل</span>
            <select class="sw-input" name="offset_account_id" required data-offset-account>
                <option value="" selected>اختر الحساب المقابل</option>
                @foreach($formOptions['accounts'] as $account)
                    <option value="{{ $account['id'] }}">{{ $account['code'] }} — {{ $account['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="sw-field"><span class="sw-field__label">البيان</span><input class="sw-input" name="description" required></label>
        <label class="sw-field"><span class="sw-field__label">المرجع</span><input class="sw-input" name="reference"></label>
    </div>
    <div class="cash-operation-actions"><button class="sw-button sw-button--primary">حفظ كمسودة</button></div>
</form>
@endif
<div class="sw-card cash-operation-table-card"><div class="cash-operation-table-scroll"><table class="sw-table"><thead><tr><th>الرقم</th><th>التاريخ</th><th>القيمة</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>
@foreach($operations as $operation)
<tr><td><strong>{{ $operation->document_number }}</strong></td><td>{{ $operation->document_date->toDateString() }}</td><td>{{ $operation->amount }}</td><td><span class="cash-operation-status">{{ $statusLabels[$operation->status] ?? 'حالة غير معروفة' }}</span></td><td>
@if($action = $actions[$operation->status] ?? null)
    @if(auth()->user()->hasPermission('treasury.'.$permissionBase.'.'.$action[0]))
    <form method="POST" action="{{ route('treasury.'.$plural.'.action', [$operation, $action[0]]) }}">@csrf
        @if($action[0] === 'reverse')<input type="hidden" name="reason" value="عكس معتمد لعملية خزينة">@endif
        <button class="sw-button sw-button--secondary">{{ $action[1] }}</button>
    </form>
    @endif
@endif
</td></tr>
@endforeach
</tbody></table></div></div>{{ $operations->links() }}
</div>
<script>
document.querySelectorAll('[data-cash-operation-form]').forEach(function (form) {
    const branch = form.querySelector('[data-cash-branch]');
    const box = form.querySelector('[data-cash-box]');
    const session = form.querySelector('[data-cash-session]');
    const account = form.querySelector('[data-offset-account]');
    let options = @json($formOptions);

    function option(value, label, attributes = {}) {
        const item = document.createElement('option');
        item.value = value;
        item.textContent = label;
        Object.entries(attributes).forEach(([name, attributeValue]) => item.dataset[name] = attributeValue);
        return item;
    }

    function resetSelect(select, label) {
        select.replaceChildren(option('', label));
    }

    function renderSessions() {
        resetSelect(session, box.value ? 'بدون جلسة للخزائن التي لا تتطلب شيفت' : 'اختر الخزينة أولًا');
        options.sessions
            .filter(item => String(item.cash_box_id) === box.value)
            .forEach(item => session.append(option(item.id, `${item.number} — ${item.cash_box_name}`)));
        session.required = box.selectedOptions[0]?.dataset.requiresSession === '1';
    }

    function renderOptions() {
        resetSelect(box, 'اختر الخزينة');
        options.cash_boxes.forEach(item => box.append(option(
            item.id,
            `${item.code} — ${item.name}`,
            { requiresSession: item.requires_session ? '1' : '0' }
        )));
        resetSelect(account, 'اختر الحساب المقابل');
        options.accounts.forEach(item => account.append(option(item.id, `${item.code} — ${item.name}`)));
        renderSessions();
    }

    branch.addEventListener('change', async function () {
        options = { cash_boxes: [], sessions: [], accounts: [] };
        renderOptions();
        const url = new URL(form.dataset.optionsUrl, window.location.origin);
        url.searchParams.set('direction', form.dataset.direction);
        url.searchParams.set('branch_id', branch.value);
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (! response.ok || branch.value !== url.searchParams.get('branch_id')) {
            return;
        }
        options = await response.json();
        renderOptions();
    });
    box.addEventListener('change', renderSessions);
    renderSessions();
});
</script>
@endsection
