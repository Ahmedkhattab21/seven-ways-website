@extends('layouts.app')
@section('title', 'قواعد المطابقة')
@section('page-title', 'قواعد المطابقة البنكية')
@section('content')
<div class="sw-card"><p>القواعد قابلة للتفسير ولا تستخدم Regex أو AI، ولا تنشئ قيودًا محاسبية.</p></div>
@if(auth()->user()->hasPermission('treasury.matching_rules.create'))
<form class="sw-card" method="POST" action="{{ route('treasury.matching-rules.store') }}">@csrf<div class="sw-form-grid">
<input name="name" required placeholder="اسم القاعدة"><select name="bank_account_id"><option value="">كل حسابات الشركة</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select>
<input name="priority" type="number" value="100" min="1"><select name="condition_type"><option value="reference_exact">Reference exact</option><option value="reference_contains">Reference contains</option><option value="description_contains">Description contains</option><option value="transaction_code">Transaction code</option><option value="amount_range">Amount range</option></select>
<input name="condition_value" placeholder="القيمة"><select name="result_type"><option value="suggest_match">Suggest match</option><option value="suggest_adjustment">Suggest adjustment</option><option value="ignore">Ignore</option></select>
<input type="hidden" name="auto_match" value="0"><label><input type="checkbox" name="auto_match" value="1"> Auto-match محكوم</label><input name="minimum_confidence" type="number" value="90" min="0" max="100"><input type="hidden" name="is_active" value="1">
</div><button class="sw-btn">حفظ القاعدة</button></form>@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الأولوية</th><th>الاسم</th><th>الحساب</th><th>الشرط</th><th>النتيجة</th><th>Auto</th><th>الحد</th><th>نشط</th></tr></thead><tbody>@foreach($rules as $rule)<tr><td>{{ $rule->priority }}</td><td>{{ $rule->name }}</td><td>{{ $rule->bankAccount?->account_name ?? 'الشركة' }}</td><td>{{ $rule->condition_type }}: {{ $rule->condition_value }}</td><td>{{ $rule->result_type }}</td><td>{{ $rule->auto_match ? 'نعم':'لا' }}</td><td>{{ $rule->minimum_confidence }}</td><td>{{ $rule->is_active ? 'نعم':'لا' }}</td></tr>@endforeach</tbody></table></div>
@endsection
