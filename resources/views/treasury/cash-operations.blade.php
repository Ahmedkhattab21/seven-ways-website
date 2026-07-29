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
@if($openSessions->isEmpty())<div class="sw-alert sw-alert--warning">لا توجد جلسة خزينة جاهزة للتشغيل. أكمل العد الافتتاحي واعتماده أولًا.</div>@endif
<div class="sw-card cash-operation-notice"><p>لا تستخدم هذه الشاشة بدل تحصيلات العملاء أو مدفوعات الموردين المرتبطة بالفواتير.</p></div>
@if(auth()->user()->hasPermission('treasury.'.$permissionBase.'.create'))
<form class="sw-card cash-operation-form" method="POST" action="{{ route('treasury.'.$plural.'.store') }}">@csrf
    <div class="sw-form-grid">
        <label class="sw-field"><span class="sw-field__label">الفرع</span><select class="sw-input" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
        <label class="sw-field"><span class="sw-field__label">الخزينة</span><select class="sw-input" name="cash_box_id" required data-cash-box>@foreach($cashBoxes as $box)<option value="{{ $box->id }}" data-requires-session="{{ $box->requires_shift_opening ? '1' : '0' }}">{{ $box->name }}</option>@endforeach</select></label>
        <label class="sw-field"><span class="sw-field__label">جلسة الخزينة</span><select class="sw-input" name="cash_box_session_id" data-cash-session><option value="">بدون جلسة للخزائن التي لا تتطلب شيفت</option>@foreach($openSessions as $session)<option value="{{ $session->id }}">{{ $session->session_number }} — {{ $session->cashBox->name }}</option>@endforeach</select></label>
        @if($direction === 'receipt')
            <label class="sw-field"><span class="sw-field__label">نوع المقبوض</span><select class="sw-input" name="receipt_type"><option value="other_income">إيراد آخر</option><option value="employee_return">رد موظف</option><option value="supplier_refund">رد مورد</option><option value="capital_injection">إضافة رأس مال</option><option value="miscellaneous">متنوع</option></select></label>
        @else
            <label class="sw-field"><span class="sw-field__label">نوع المدفوع</span><select class="sw-input" name="payment_type"><option value="general_expense">مصروف عام</option><option value="employee_advance">سلفة موظف</option><option value="employee_reimbursement">تعويض موظف</option><option value="petty_cash">عهدة نقدية</option><option value="miscellaneous">متنوع</option></select></label>
        @endif
        <label class="sw-field"><span class="sw-field__label">تاريخ المستند</span><input class="sw-input" name="document_date" type="date" value="{{ now()->toDateString() }}" required></label>
        <input type="hidden" name="currency_id" value="{{ $company->currency_id }}"><input type="hidden" name="exchange_rate" value="1">
        <label class="sw-field"><span class="sw-field__label">المبلغ</span><input class="sw-input" name="amount" type="number" step="0.01" min="0.01" required></label>
        <label class="sw-field"><span class="sw-field__label">الحساب المقابل</span><select class="sw-input" name="offset_account_id" required>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select></label>
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
<script>document.querySelectorAll('[data-cash-box]').forEach(function(box){const session=box.closest('form').querySelector('[data-cash-session]');function sync(){session.required=box.selectedOptions[0]?.dataset.requiresSession==='1';}box.addEventListener('change',sync);sync();});</script>
@endsection
