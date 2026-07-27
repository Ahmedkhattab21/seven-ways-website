@extends('layouts.app')
@section('title', 'تسويات البنك')
@section('page-title', 'تسويات البنك المحاسبية')
@section('content')
<div class="sw-card"><p>لا يتم إنشاء قيد فرق تلقائي. كل تسوية تمر Draft → Submit → Approve → Post عبر Journal Engine.</p></div>
@if(auth()->user()->hasPermission('treasury.bank_adjustments.create'))
<form class="sw-card" method="POST" action="{{ route('treasury.bank-adjustments.store') }}">@csrf<div class="sw-form-grid">
<select name="bank_account_id" required>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select>
<select name="bank_reconciliation_session_id"><option value="">بدون جلسة</option>@foreach($sessions as $session)<option value="{{ $session->id }}">{{ $session->session_number }}</option>@endforeach</select>
<input name="bank_statement_line_id" type="number" placeholder="Statement line ID">
<select name="adjustment_type"><option value="bank_fee">Bank Fee</option><option value="interest_income">Interest Income</option><option value="interest_expense">Interest Expense</option><option value="unidentified_receipt">Unidentified Receipt</option><option value="unidentified_payment">Unidentified Payment</option><option value="rounding">Rounding</option><option value="other">Other</option></select>
<input type="date" name="adjustment_date" value="{{ now()->toDateString() }}" required><input name="exchange_rate" type="number" step="0.00000001" value="1" required><input name="amount" type="number" step="0.0001" min="0.0001" required>
<select name="offset_account_id" required>@foreach($offsetAccounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select>
<input name="description" required placeholder="الوصف"><input name="reference" placeholder="المرجع">
</div><button class="sw-btn">حفظ مسودة</button></form>@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الرقم</th><th>النوع</th><th>الحساب</th><th>التاريخ</th><th>القيمة</th><th>الحالة</th><th>القيد</th><th>إجراء</th></tr></thead><tbody>
@foreach($adjustments as $adjustment)<tr><td>{{ $adjustment->document_number }}</td><td>{{ $adjustment->adjustment_type }}</td><td>{{ $adjustment->bankAccount->account_name }}</td><td>{{ $adjustment->adjustment_date->toDateString() }}</td><td>{{ $adjustment->amount }}</td><td>{{ $adjustment->status }}</td><td>@if($adjustment->journalEntry)<a href="{{ route('accounting.journals.show',$adjustment->journalEntry) }}">{{ $adjustment->journalEntry->journal_number }}</a>@endif</td><td>@foreach(['submit','approve','post','reverse','cancel'] as $action)@if(auth()->user()->hasPermission('treasury.bank_adjustments.'.$action))<form method="POST" action="{{ route('treasury.bank-adjustments.action',[$adjustment,$action]) }}">@csrf@if($action==='reverse')<input name="reason" value="عكس تسوية موثق">@endif<button class="sw-btn">{{ $action }}</button></form>@endif @endforeach</td></tr>@endforeach
</tbody></table>{{ $adjustments->links() }}</div>
@endsection
