@extends('layouts.app')
@section('title', 'مالية الموظفين')
@section('page-title', 'الموظفون والعمولات والمصروفات')
@section('content')
<div class="sw-card">
    <h2>قواعد العمولات</h2>
    @if(auth()->user()->hasPermission('commissions.manage_rules'))
    <form method="POST" action="{{ route('employee-finance.commission-rules.store') }}" class="sw-form">@csrf
        <div class="sw-form-grid">
            <select name="employee_id"><option value="">كل الموظفين</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select>
            <select name="branch_id"><option value="">كل الفروع</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
            <select name="rule_type">@foreach(['percentage_net_sales','percentage_margin','fixed_product','fixed_service','fixed'] as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select>
            <input name="rule_value" type="number" step="0.0001" min="0.0001" placeholder="القيمة" required>
            <select name="currency_id">@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }}</option>@endforeach</select>
            <select name="expense_account_id">@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select>
            <select name="payable_account_id">@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select>
            <input name="product_id" type="number" min="1" placeholder="رقم المنتج (اختياري)">
            <input name="service_id" type="number" min="1" placeholder="رقم الخدمة (اختياري)">
            <input name="effective_from" type="date" value="{{ now()->toDateString() }}" required>
            <input name="effective_to" type="date">
            <input name="priority" type="number" value="0">
            <input type="hidden" name="is_active" value="1">
        </div>
        <button class="sw-btn">حفظ القاعدة</button>
    </form>
    @endif
    <table class="sw-table"><thead><tr><th>النوع</th><th>الموظف/الفرع</th><th>القيمة</th><th>السريان</th></tr></thead><tbody>
    @forelse($rules as $rule)<tr><td>{{ $rule->rule_type }}</td><td>{{ $rule->employee_id ?: 'عام' }} / {{ $rule->branch_id ?: 'عام' }}</td><td>{{ $rule->rule_value }} {{ $rule->currency?->code }}</td><td>{{ $rule->effective_from?->toDateString() }} — {{ $rule->effective_to?->toDateString() ?? 'مفتوح' }}</td></tr>
    @empty<tr><td colspan="4">لا توجد قواعد.</td></tr>@endforelse
    </tbody></table>
</div>

<div class="sw-card">
    <h2>استحقاقات العمولات</h2>
    <table class="sw-table"><thead><tr><th>الموظف</th><th>المصدر</th><th>الأساس</th><th>العمولة</th><th>المتبقي</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>
    @forelse($accruals as $row)<tr>
        <td>{{ $row->employee?->name }}</td><td>#{{ $row->sales_invoice_id }}</td><td>{{ $row->basis_amount }}</td>
        <td>{{ $row->commission_amount }} {{ $currencies->firstWhere('id', $row->currency_id)?->code }}</td>
        <td>{{ bcsub($row->commission_amount, $row->settled_amount, 4) }}</td><td>{{ $row->status }}</td>
        <td>@php($actions = match($row->status) {'calculated'=>['submit'],'pending_approval'=>['approve'],'approved'=>['post','reverse'],'posted'=>['reverse'],default=>[]}) @foreach($actions as $action)@if(auth()->user()->hasPermission('commissions.'.$action))<form method="POST" action="{{ route('employee-finance.commission-accruals.action', [$row, $action]) }}" style="display:inline">@csrf<button class="sw-btn">{{ $action }}</button></form>@endif @endforeach</td>
    </tr>@empty<tr><td colspan="7">لا توجد استحقاقات.</td></tr>@endforelse
    </tbody></table>
</div>

<div class="sw-card">
    <h2>مطالبة مصروف جديدة</h2>
    @if(auth()->user()->hasPermission('employee_expenses.create'))
    <form method="POST" action="{{ route('employee-finance.expense-claims.store') }}" class="sw-form">@csrf
        <div class="sw-form-grid">
            <select name="employee_id" required>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select>
            <select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
            <select name="currency_id" required>@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }}</option>@endforeach</select>
            <select name="payable_account_id" required>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select>
            <input name="claim_date" type="date" value="{{ now()->toDateString() }}" required>
            <input name="business_purpose" placeholder="غرض العمل" required>
            <input name="items[0][expense_date]" type="date" value="{{ now()->toDateString() }}" required>
            <input name="items[0][description]" placeholder="وصف البند" required>
            <select name="items[0][expense_account_id]" required>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select>
            <select name="items[0][tax_id]"><option value="">بدون ضريبة</option>@foreach($taxes as $tax)<option value="{{ $tax->id }}">{{ $tax->name }} — {{ $tax->rate }}%</option>@endforeach</select>
            <input name="items[0][net_amount]" type="number" step="0.0001" min="0.0001" placeholder="الصافي" required>
        </div>
        <button class="sw-btn">حفظ مسودة</button>
    </form>
    @endif
    <table class="sw-table"><thead><tr><th>الرقم</th><th>الموظف</th><th>الإجمالي</th><th>الحالة</th><th>القيد</th><th>الإجراء</th></tr></thead><tbody>
    @forelse($claims as $claim)<tr><td>{{ $claim->claim_number }}</td><td>{{ $claim->employee?->name }}</td><td>{{ $claim->total_amount }} {{ $currencies->firstWhere('id', $claim->currency_id)?->code }}</td><td>{{ $claim->status }}</td><td>{{ $claim->journal_entry_id ? '#'.$claim->journal_entry_id : '—' }}</td><td>@php($actions = match($claim->status) {'draft'=>['submit'],'pending_approval'=>['approve','reject'],'approved'=>['post'],'posted'=>['pay','reverse'],default=>[]}) @foreach($actions as $action)@if(auth()->user()->hasPermission('employee_expenses.'.$action))<form method="POST" action="{{ route('employee-finance.expense-claims.action', [$claim, $action]) }}" style="display:inline">@csrf<button class="sw-btn">{{ $action }}</button></form>@endif @endforeach</td></tr>
    @empty<tr><td colspan="6">لا توجد مطالبات.</td></tr>@endforelse
    </tbody></table>
</div>

<div class="sw-card">
    <h2>السلف والعهد</h2>
    @if(auth()->user()->hasPermission('employee_advances.create'))
    <form method="POST" action="{{ route('employee-finance.advances.store') }}" class="sw-form">@csrf
        <div class="sw-form-grid">
            <select name="employee_id" required>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select>
            <select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
            <select name="currency_id" required>@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }}</option>@endforeach</select>
            <select name="advance_type"><option value="advance">سلفة</option><option value="custody">عهدة</option></select>
            <select name="receivable_account_id" required>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select>
            <input name="request_date" type="date" value="{{ now()->toDateString() }}" required>
            <input name="purpose" placeholder="الغرض" required>
            <input name="amount" type="number" step="0.0001" min="0.0001" placeholder="المبلغ" required>
        </div>
        <button class="sw-btn">حفظ مسودة</button>
    </form>
    @endif
    <table class="sw-table"><thead><tr><th>الرقم</th><th>النوع</th><th>الموظف</th><th>المبلغ</th><th>المتبقي</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>
    @forelse($advances as $advance)<tr><td>{{ $advance->advance_number }}</td><td>{{ $advance->advance_type }}</td><td>{{ $advance->employee?->name }}</td><td>{{ $advance->amount }} {{ $currencies->firstWhere('id', $advance->currency_id)?->code }}</td><td>{{ bcsub($advance->amount, $advance->settled_amount, 4) }}</td><td>{{ $advance->status }}</td><td>@php($actions = match($advance->status) {'draft'=>['submit'],'submitted'=>['approve'],'approved'=>['disburse'],'disbursed'=>['reverse'],'settled'=>['close'],default=>[]}) @foreach($actions as $action)@if(auth()->user()->hasPermission('employee_advances.'.$action))<form method="POST" action="{{ route('employee-finance.advances.action', [$advance, $action]) }}" style="display:inline">@csrf<button class="sw-btn">{{ $action }}</button></form>@endif @endforeach</td></tr>
    @empty<tr><td colspan="7">لا توجد سلف أو عهد.</td></tr>@endforelse
    </tbody></table>
</div>
@endsection
