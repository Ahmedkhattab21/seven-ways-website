@extends('layouts.app')
@section('title', 'جلسات الخزائن والجرد')
@section('page-title', 'جلسات الخزائن والجرد النقدي')
@section('content')
@if(auth()->user()->hasPermission('treasury.cash_sessions.open'))
<form class="sw-card" method="POST" action="{{ route('treasury.cash-sessions.store') }}">@csrf
    <div class="sw-form-grid">
        <select name="cash_box_id" required><option value="">الخزينة</option>@foreach($cashBoxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach</select>
        <select name="custodian_user_id" required><option value="">أمين الخزينة</option>@foreach($custodians as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
        <input name="business_date" type="date" value="{{ now()->toDateString() }}" required>
        <input name="opening_notes" placeholder="ملاحظات الفتح">
    </div>
    <button class="sw-btn">فتح جلسة</button>
</form>
@endif
@foreach($sessions as $session)
<section class="sw-card">
    <h3>{{ $session->session_number }} — {{ $session->cashBox->name }} — {{ $session->status }}</h3>
    <p>Opening Book: {{ $session->opening_book_balance }} | Opening Count: {{ $session->opening_counted_balance }} | Difference: {{ $session->opening_difference }} | Custodian #{{ $session->custodian_user_id }}</p>
    @if(in_array($session->status, ['opened','counting']))
    <form method="POST" action="{{ route('treasury.cash-sessions.counts.store', $session) }}">@csrf
        <div class="sw-form-grid">
            <select name="count_type"><option value="opening">Opening</option><option value="interim">Interim</option><option value="closing">Closing</option><option value="surprise">Surprise</option></select>
            <input name="lines[0][denomination]" type="number" step="0.01" min="0.01" placeholder="Denomination" required>
            <input name="lines[0][quantity]" type="number" min="1" placeholder="Quantity" required>
            <input name="notes" placeholder="ملاحظات">
        </div>
        <button class="sw-btn">تسجيل العد</button>
    </form>
    @endif
    @foreach($session->counts as $count)
        <p>{{ $count->count_type }}: {{ $count->counted_total }} / Book {{ $count->book_total }} / Difference {{ $count->difference }} / {{ $count->status }}</p>
    @endforeach
    @foreach(['start_counting','submit','approve','close'] as $action)
        <form method="POST" action="{{ route('treasury.cash-sessions.action', [$session, $action]) }}" style="display:inline">@csrf<button class="sw-btn">{{ $action }}</button></form>
    @endforeach
</section>
@endforeach
{{ $sessions->links() }}
@endsection
