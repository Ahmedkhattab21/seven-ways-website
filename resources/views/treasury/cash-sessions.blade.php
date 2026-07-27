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
    @if(in_array($session->status, ['opened','counting']) && auth()->user()->hasPermission('treasury.cash_sessions.count'))
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
        <div>
            <p>{{ $count->count_type }}: {{ $count->counted_total }} / Book {{ $count->book_total }} / Difference {{ $count->difference }} / {{ $count->status }}</p>
            @php($countActions = ['draft'=>['submit','count'],'submitted'=>['review','count'],'reviewed'=>['approve','approve']])
            @if($countAction = $countActions[$count->status] ?? null)
                @if(auth()->user()->hasPermission('treasury.cash_sessions.'.$countAction[1]))
                <form method="POST" action="{{ route('treasury.cash-counts.action', [$count, $countAction[0]]) }}" style="display:inline">@csrf<button class="sw-btn">{{ $countAction[0] }}</button></form>
                @endif
            @endif
            @if($count->status === 'approved' && bccomp((string) $count->difference, '0', 4) !== 0)
                @if(!$count->adjustment && auth()->user()->hasPermission('treasury.cash_over_short.view'))
                <form method="POST" action="{{ route('treasury.cash-counts.adjustment', $count) }}">@csrf<input name="description" value="مراجعة فرق عد صندوق QA" required><button class="sw-btn">إنشاء فرق الصندوق</button></form>
                @elseif($count->adjustment)
                    <p>Over/Short: {{ $count->adjustment->adjustment_type }} — {{ $count->adjustment->amount }} — {{ $count->adjustment->status }}</p>
                    @php($adjustmentActions = ['draft'=>['submit','view'],'pending_approval'=>['approve','approve'],'approved'=>['post','post'],'posted'=>['reverse','post']])
                    @if($adjustmentAction = $adjustmentActions[$count->adjustment->status] ?? null)
                        @if(auth()->user()->hasPermission('treasury.cash_over_short.'.$adjustmentAction[1]))
                        <form method="POST" action="{{ route('treasury.cash-over-short.action', [$count->adjustment, $adjustmentAction[0]]) }}" style="display:inline">@csrf
                            @if($adjustmentAction[0] === 'reverse')<input type="hidden" name="reason" value="Approved QA reversal">@endif
                            <button class="sw-btn">{{ $adjustmentAction[0] }}</button>
                        </form>
                        @endif
                    @endif
                @endif
            @endif
        </div>
    @endforeach
    @php($sessionActions = ['opened'=>['start_counting','count'],'counting'=>['submit','submit'],'pending_approval'=>['approve','approve'],'approved'=>['close','close'],'closed'=>['reopen','reopen']])
    @if($sessionAction = $sessionActions[$session->status] ?? null)
        @if(auth()->user()->hasPermission('treasury.cash_sessions.'.$sessionAction[1]))
        <form method="POST" action="{{ route('treasury.cash-sessions.action', [$session, $sessionAction[0]]) }}" style="display:inline">@csrf<button class="sw-btn">{{ $sessionAction[0] }}</button></form>
        @endif
    @endif
</section>
@endforeach
{{ $sessions->links() }}
@endsection
