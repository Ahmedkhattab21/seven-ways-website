@extends('layouts.app')
@section('title', $direction === 'received' ? 'الشيكات الواردة' : 'الشيكات الصادرة')
@section('page-title', $direction === 'received' ? 'الشيكات الواردة' : 'الشيكات الصادرة')
@section('content')
@if(auth()->user()->hasPermission('treasury.cheques.create'))
<form class="sw-card" method="POST" action="{{ route('treasury.cheques.store') }}">@csrf
    <input type="hidden" name="direction" value="{{ $direction }}"><input type="hidden" name="currency_id" value="{{ $company->currency_id }}">
    <div class="sw-form-grid">
        <select name="branch_id">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
        <input name="cheque_number" required placeholder="Cheque number">
        <select name="bank_id">@foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name_ar }}</option>@endforeach</select>
        <select name="bank_account_id"><option value="">Bank account</option>@foreach($bankAccounts as $account)<option value="{{ $account->id }}">{{ $account->account_name }}</option>@endforeach</select>
        <input name="amount" type="number" min="0.01" step="0.01" required>
        <input name="issue_date" type="date" value="{{ now()->toDateString() }}" required>
        <input name="due_date" type="date" value="{{ now()->addDays(30)->toDateString() }}" required>
        <select name="clearing_account_id">@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select>
        <select name="offset_account_id">@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select>
        @if($direction === 'received')<input name="drawer_name" placeholder="Drawer"><input name="received_date" type="date" value="{{ now()->toDateString() }}">@else<input name="beneficiary_name" placeholder="Beneficiary">@endif
    </div><button class="sw-btn">تسجيل شيك</button>
</form>
@endif
@foreach($cheques as $cheque)
<section class="sw-card">
    <h3>{{ auth()->user()->hasPermission('treasury.cheques.view_sensitive') ? $cheque->cheque_number : $cheque->maskedNumber() }} — {{ $cheque->amount }} — {{ $cheque->status }}</h3>
    <p>@foreach($cheque->histories as $history){{ $history->to_status }} ({{ $history->changed_at }}) → @endforeach</p>
    @php($chequeActions = match($cheque->status) {
        'draft' => ['submit','cancel'],
        'received' => ['approve','cancel'],
        'on_hand' => $cheque->direction === 'received' ? ['deposit','return'] : [],
        'issued' => $cheque->approved_by ? ['present','return'] : ['approve','cancel'],
        'deposited', 'under_collection' => ['clear','return'],
        'presented' => ['clear','return'],
        'bounced' => ['return','replace'],
        'returned', 'cancelled' => ['replace'],
        default => []
    })
    @foreach($chequeActions as $action)
        @continue($action === 'replace')
        @if(auth()->user()->hasPermission('treasury.cheques.'.$action))
        <form method="POST" action="{{ route('treasury.cheques.action', [$cheque, $action]) }}" style="display:inline">@csrf<input type="hidden" name="date" value="{{ now()->toDateString() }}"><input type="hidden" name="reason" value="Approved cheque action"><button class="sw-btn">{{ $action }}</button></form>
        @endif
    @endforeach
    @if($cheque->status === 'cleared' && auth()->user()->hasPermission('treasury.cheques.bounce'))
    <form method="POST" action="{{ route('treasury.cheques.bounce', $cheque) }}" style="display:inline">@csrf<input type="hidden" name="date" value="{{ now()->toDateString() }}"><input type="hidden" name="reason" value="QA cheque bounce"><button class="sw-btn">bounce</button></form>
    @endif
    @if(in_array($cheque->status, ['bounced','returned','cancelled']) && auth()->user()->hasPermission('treasury.cheques.replace'))
    <form method="POST" action="{{ route('treasury.cheques.action', [$cheque, 'replace']) }}" class="sw-form-grid">@csrf
        <input name="replacement_cheque_number" required placeholder="Replacement number">
        <input name="replacement_issue_date" type="date" value="{{ now()->toDateString() }}" required>
        <input name="replacement_due_date" type="date" value="{{ now()->addDays(30)->toDateString() }}" required>
        <button class="sw-btn">replace</button>
    </form>
    @endif
    @if($cheque->direction === 'received' && $cheque->status === 'on_hand' && auth()->user()->hasPermission('treasury.cheques.endorse'))
    <form method="POST" action="{{ route('treasury.cheques.endorse', $cheque) }}" class="sw-form-grid">@csrf
        <input type="hidden" name="endorsed_to_type" value="other">
        <input name="endorsed_to_name" required placeholder="Endorsed to">
        <input name="endorsement_date" type="date" value="{{ now()->toDateString() }}" required>
        <button class="sw-btn">endorse</button>
    </form>
    @endif
    @foreach($cheque->endorsements as $endorsement)
        <p>Endorsement: {{ $endorsement->endorsed_to_name }} — {{ $endorsement->status }}</p>
        @if($endorsement->status === 'pending_approval' && auth()->user()->can('approve', $endorsement))
        <form method="POST" action="{{ route('treasury.cheque-endorsements.approve', $endorsement) }}">@csrf<button class="sw-btn">approve endorsement</button></form>
        @endif
    @endforeach
</section>
@endforeach
{{ $cheques->links() }}
@endsection
