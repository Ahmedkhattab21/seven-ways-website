@extends('layouts.app')
@section('title', 'الحسابات البنكية')
@section('page-title', 'الحسابات البنكية')
@section('content')
@if(auth()->user()->hasPermission('treasury.bank_accounts.create'))
<form class="sw-card" method="POST" action="{{ route('treasury.bank-accounts.store') }}">
    @csrf
    <h3>حساب بنكي جديد</h3>
    <div class="sw-form-grid">
        <label>البنك<select name="bank_id" required><option value="">اختر البنك</option>@foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name_ar }}</option>@endforeach</select></label>
        <label>الفرع<select name="branch_id"><option value="">كل الشركة</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
        <label>كود الحساب<input name="account_code" required></label><label>اسم الحساب<input name="account_name" required></label>
        <label>IBAN<input name="iban"></label><label>العملة<select name="currency_id" required>@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }}</option>@endforeach</select></label>
        <label>حساب GL<select name="gl_account_id" required><option value="">اختر حساب GL</option>@foreach($glAccounts->where('is_bank_account', true) as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select></label>
        <label>نوع الحساب<select name="account_type" required>@foreach(['current','savings','merchant','collection','payroll','credit_card','other'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></label>
        <label><input type="checkbox" name="is_primary" value="1"> حساب رئيسي</label>
    </div>
    <button class="sw-btn">حفظ كمسودة</button>
</form>
@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الكود</th><th>البنك</th><th>الفرع</th><th>العملة</th><th>الرصيد الدفتري</th><th>الحالة</th><th>صلاحية الفرع</th><th>الإجراء</th></tr></thead><tbody>
@foreach($bankAccounts as $account)
<tr><td>{{ $account->account_code }}</td><td>{{ $account->bank->name_ar }}</td><td>{{ $account->branch?->name ?? 'كل الشركة' }}</td><td>{{ $account->currency->code }}</td><td>{{ $balances[$account->id]['book_balance'] }}</td><td>{{ $account->status }}</td><td>
@if(auth()->user()->hasPermission('treasury.bank_accounts.manage_branch_access'))
<form method="POST" action="{{ route('treasury.bank-accounts.access', $account) }}">@csrf
<select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($account->branch_id === $branch->id)>{{ $branch->name }}</option>@endforeach</select>
<label><input type="checkbox" name="can_view" value="1" checked> عرض</label><label><input type="checkbox" name="can_receive" value="1" checked> استقبال</label><label><input type="checkbox" name="can_pay" value="1"> دفع</label><label><input type="checkbox" name="can_transfer" value="1"> تحويل</label>
<button class="sw-btn">حفظ الصلاحية</button></form>
@else — @endif</td><td>@foreach(['activate','suspend','close'] as $action)@if(auth()->user()->hasPermission('treasury.bank_accounts.'.$action))<form method="POST" action="{{ route('treasury.bank-accounts.action',[$account,$action]) }}">@csrf<input type="hidden" name="reason" value="تحديث حالة الحساب البنكي"><button class="sw-btn">{{ $action }}</button></form>@endif @endforeach</td></tr>
@endforeach
</tbody></table></div>
@endsection
