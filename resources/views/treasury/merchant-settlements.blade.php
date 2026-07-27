@extends('layouts.app')
@section('title', 'تسويات نقاط البيع')
@section('page-title', 'تسويات نقاط البيع والتجار')
@section('content')
<form class="sw-card" method="POST" action="{{ route('treasury.merchant-settlements.store') }}">@csrf
    <input type="hidden" name="currency_id" value="{{ $company->currency_id }}">
    <div class="sw-form-grid">
        <select name="branch_id">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
        <select name="bank_account_id">@foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select>
        <select name="payment_method_id">@foreach($paymentMethods as $method)<option value="{{ $method->id }}">{{ $method->name }}</option>@endforeach</select>
        <input name="settlement_reference" required placeholder="Settlement reference">
        <input name="period_start" type="date" value="{{ now()->startOfMonth()->toDateString() }}">
        <input name="period_end" type="date" value="{{ now()->toDateString() }}">
        <input name="settlement_date" type="date" value="{{ now()->toDateString() }}">
        <input name="fees_amount" type="number" min="0" step="0.01" value="0">
        <input name="tax_amount" type="number" min="0" step="0.01" value="0">
        <select name="lines[0][source_id]">@foreach($sources as $source)<option value="{{ $source->id }}">{{ $source->payment_number }} — {{ $source->amount }}</option>@endforeach</select>
        <input type="hidden" name="lines[0][source_type]" value="customer_payment">
        <input name="lines[0][allocated_amount]" type="number" min="0.01" step="0.01" required placeholder="Allocated">
    </div><button class="sw-btn">إنشاء التسوية</button>
</form>
<div class="sw-card"><table class="sw-table"><thead><tr><th>الرقم</th><th>Gross</th><th>Fees</th><th>VAT</th><th>Net</th><th>Status</th></tr></thead><tbody>
@foreach($settlements as $settlement)<tr><td>{{ $settlement->document_number }}</td><td>{{ $settlement->gross_amount }}</td><td>{{ $settlement->fees_amount }}</td><td>{{ $settlement->tax_amount }}</td><td>{{ $settlement->net_amount }}</td><td>{{ $settlement->status }}</td></tr>@endforeach
</tbody></table></div>{{ $settlements->links() }}
@endsection
