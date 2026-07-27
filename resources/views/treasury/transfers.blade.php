@extends('layouts.app')
@section('title', 'تحويلات الخزينة')
@section('page-title', 'تحويلات الخزينة')
@section('content')
<div class="sw-card"><p>التحويل المعتمد يُنفذ ويُرحل مرة واحدة. التحويل المكتمل غير قابل للتعديل، والعكس ينشئ قيدًا معاكسًا في فترة مفتوحة.</p></div>
@if(auth()->user()->hasPermission('treasury.transfers.create'))
<form class="sw-card" method="POST" action="{{ route('treasury.transfers.store') }}">@csrf
    <div class="sw-form-grid">
        <select name="transfer_type"><option value="transfer">Transfer</option><option value="cash_deposit">Cash deposit</option><option value="cash_withdrawal">Cash withdrawal</option></select>
        <select name="from_type"><option value="bank">Bank</option><option value="cash_box">Cash Box</option></select>
        <select name="from_bank_account_id"><option value="">From Bank</option>@foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select>
        <select name="from_cash_box_id"><option value="">From Cash Box</option>@foreach($cashBoxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach</select>
        <select name="to_type"><option value="bank">Bank</option><option value="cash_box">Cash Box</option></select>
        <select name="to_bank_account_id"><option value="">To Bank</option>@foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select>
        <select name="to_cash_box_id"><option value="">To Cash Box</option>@foreach($cashBoxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach</select>
        <select name="branch_id">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
        <select name="currency_id">@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }}</option>@endforeach</select>
        <input name="exchange_rate" value="1" type="number" step="1" readonly>
        <input name="amount" required type="number" step="0.01" min="0.01">
        <input name="fees_amount" value="0" type="number" step="0.01">
        <input name="transfer_date" type="date" value="{{ now()->toDateString() }}" required>
    </div>
    <button class="sw-btn">حفظ كمسودة</button>
</form>
@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الرقم</th><th>النوع</th><th>التاريخ</th><th>القيمة</th><th>الحالة</th><th>القيد</th><th>الإجراء</th></tr></thead><tbody>
@foreach($transfers as $transfer)<tr>
    <td>{{ $transfer->document_number }}</td><td>{{ $transfer->transfer_type }}</td>
    <td>{{ $transfer->transfer_date->toDateString() }}</td><td>{{ $transfer->amount }} + {{ $transfer->fees_amount }}</td>
    <td>{{ $transfer->status }} @if($transfer->failure_reason)<small>{{ $transfer->failure_reason }}</small>@endif</td>
    <td>{{ $transfer->journal_entry_id ?: '—' }}</td><td>
        @php($workflowActions = match($transfer->status) {
            'draft' => ['submit','cancel'], 'pending_approval' => ['approve','cancel'],
            'approved' => ['cancel'], default => []
        })
        @foreach($workflowActions as $action)
            @if(auth()->user()->hasPermission('treasury.transfers.'.$action))
            <form method="POST" action="{{ route('treasury.transfers.action',[$transfer,$action]) }}" style="display:inline">@csrf
                @if($action === 'cancel')<input type="hidden" name="reason" value="Approved transfer cancellation">@endif
                <button class="sw-btn">{{ $action }}</button>
            </form>
            @endif
        @endforeach
        @if(in_array($transfer->status, ['approved','failed']) && auth()->user()->hasPermission('treasury.transfers.process'))
        <form method="POST" action="{{ route('treasury.transfers.process',$transfer) }}" style="display:inline">@csrf<button class="sw-btn">process</button></form>
        @endif
        @if($transfer->status === 'completed' && auth()->user()->hasPermission('treasury.transfers.reverse'))
        <form method="POST" action="{{ route('treasury.transfers.reverse',$transfer) }}" style="display:inline">@csrf<input type="hidden" name="reason" value="Approved transfer reversal"><button class="sw-btn">reverse</button></form>
        @endif
    </td>
</tr>@endforeach
</tbody></table></div>
@endsection
