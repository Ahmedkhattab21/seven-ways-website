@extends('layouts.app')
@php($plural = $direction === 'receipt' ? 'cash-receipts' : 'cash-payments')
@php($permissionBase = str_replace('-', '_', $plural))
@section('title', $direction === 'receipt' ? 'المقبوضات النقدية' : 'المدفوعات النقدية')
@section('page-title', $direction === 'receipt' ? 'المقبوضات النقدية العامة' : 'المدفوعات النقدية العامة')
@section('content')
@if($openSessions->isEmpty())<div class="sw-card"><p>لا توجد جلسة خزينة جاهزة للتشغيل. أكمل العد الافتتاحي واعتماده أولًا.</p></div>@endif
<div class="sw-card"><p>لا تستخدم هذه الشاشة بدل تحصيلات العملاء أو مدفوعات الموردين المرتبطة بالفواتير.</p></div>
@if(auth()->user()->hasPermission('treasury.'.$permissionBase.'.create'))
<form class="sw-card" method="POST" action="{{ route('treasury.'.$plural.'.store') }}">@csrf
    <div class="sw-form-grid">
        <select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
        <select name="cash_box_id" required>@foreach($cashBoxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach</select>
        <select name="cash_box_session_id"><option value="">جلسة الصندوق — اختيارية</option>@foreach($openSessions as $session)<option value="{{ $session->id }}">{{ $session->session_number }} — {{ $session->cashBox->name }}</option>@endforeach</select>
        @if($direction === 'receipt')
            <select name="receipt_type"><option value="other_income">Other income</option><option value="employee_return">Employee return</option><option value="supplier_refund">Supplier refund</option><option value="capital_injection">Capital injection</option><option value="miscellaneous">Miscellaneous</option></select>
        @else
            <select name="payment_type"><option value="general_expense">General expense</option><option value="employee_advance">Employee advance</option><option value="employee_reimbursement">Employee reimbursement</option><option value="petty_cash">Petty cash</option><option value="miscellaneous">Miscellaneous</option></select>
        @endif
        <input name="document_date" type="date" value="{{ now()->toDateString() }}" required>
        <input type="hidden" name="currency_id" value="{{ $company->currency_id }}"><input type="hidden" name="exchange_rate" value="1">
        <input name="amount" type="number" step="0.01" min="0.01" required placeholder="Amount">
        <select name="offset_account_id" required>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select>
        <input name="description" required placeholder="Description"><input name="reference" placeholder="Reference">
    </div>
    <button class="sw-btn">حفظ مسودة</button>
</form>
@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الرقم</th><th>التاريخ</th><th>القيمة</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>
@foreach($operations as $operation)<tr><td>{{ $operation->document_number }}</td><td>{{ $operation->document_date->toDateString() }}</td><td>{{ $operation->amount }}</td><td>{{ $operation->status }}</td><td>
@php($actions = ['draft'=>'submit','pending_approval'=>'approve','approved'=>'post','posted'=>'reverse'])
@if($action = $actions[$operation->status] ?? null)
    @if(auth()->user()->hasPermission('treasury.'.$permissionBase.'.'.$action))
    <form method="POST" action="{{ route('treasury.'.$plural.'.action', [$operation, $action]) }}" style="display:inline">@csrf
        @if($action === 'reverse')<input type="hidden" name="reason" value="Approved treasury reversal">@endif
        <button class="sw-btn">{{ $action }}</button>
    </form>
    @endif
@endif
</td></tr>@endforeach
</tbody></table></div>{{ $operations->links() }}
@endsection
