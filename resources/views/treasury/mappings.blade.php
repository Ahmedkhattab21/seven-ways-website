@extends('layouts.app')
@section('title', 'توجيه وسائل الدفع')
@section('page-title', 'توجيه وسائل الدفع')
@section('content')
@php
    $operationLabels = [
        'receipt' => 'قبض', 'payment' => 'صرف', 'refund' => 'استرداد',
        'deposit' => 'إيداع بنكي', 'withdrawal' => 'سحب بنكي',
        'transfer' => 'تحويل', 'merchant_settlement' => 'تسوية نقاط البيع',
    ];
@endphp
@if(auth()->user()->hasPermission('treasury.mappings.update'))
<form class="sw-card" method="POST" action="{{ route('treasury.mappings.store') }}">
    @csrf
    <div class="sw-form-grid">
        <label>وسيلة الدفع
            <select name="payment_method_id" required>
                @foreach($paymentMethods as $method)
                    <option value="{{ $method->id }}">{{ $method->name }} — {{ $method->code }}</option>
                @endforeach
            </select>
        </label>
        <label>الفرع
            <select name="branch_id"><option value="">افتراضي للشركة</option>
                @foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
            </select>
        </label>
        <label>نوع العملية
            <select name="operation_type">@foreach($operationLabels as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select>
        </label>
        <label>حساب GL مباشر
            <select name="account_id"><option value="">بدون حساب GL</option>
                @foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar ?: $account->name_en }}</option>@endforeach
            </select>
        </label>
        <label>الحساب البنكي
            <select name="bank_account_id"><option value="">بدون حساب بنكي</option>
                @foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach
            </select>
        </label>
        <label>الخزينة
            <select name="cash_box_id"><option value="">بدون خزينة</option>
                @foreach($cashBoxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach
            </select>
        </label>
    </div>
    <p class="text-muted">يجب اختيار هدف واحد فقط: حساب GL أو حساب بنكي أو خزينة.</p>
    <button class="sw-btn">حفظ التوجيه</button>
</form>
@endif
<div class="sw-card"><table class="sw-table"><thead><tr>
    <th>وسيلة الدفع</th><th>الفرع</th><th>العملية</th><th>الهدف</th><th>الحالة</th>
</tr></thead><tbody>
@forelse($mappings as $mapping)
    @php
        $payment = $mapping->paymentMethod;
        $target = $mapping->account
            ? 'حساب GL: '.$mapping->account->account_code.' — '.($mapping->account->name_ar ?: $mapping->account->name_en)
            : ($mapping->bankAccount ? 'حساب بنكي: '.$mapping->bankAccount->account_name : ($mapping->cashBox ? 'خزينة: '.$mapping->cashBox->name.' — '.($mapping->cashBox->branch?->name ?? '') : 'غير محدد'));
    @endphp
    <tr>
        <td>{{ $payment?->name ?? 'وسيلة دفع غير متاحة' }} @if($payment?->code)<small>— {{ $payment->code }}</small>@endif</td>
        <td>{{ $mapping->branch?->name ?? 'افتراضي للشركة' }}</td>
        <td>{{ $operationLabels[$mapping->operation_type] ?? $mapping->operation_type }}</td>
        <td>{{ $target }}</td>
        <td>{{ $mapping->is_active ? 'نشط' : 'معطل' }}</td>
    </tr>
@empty
    <tr><td colspan="5">لا توجد توجيهات دفع.</td></tr>
@endforelse
</tbody></table></div>
@endsection
