@extends('layouts.app')
@section('title', 'جلسات الخزائن والجرد')
@section('page-title', 'جلسات الخزائن والجرد النقدي')
@section('content')
@php($countTypes = ['opening' => 'افتتاحي', 'interim' => 'مرحلي', 'closing' => 'ختامي', 'surprise' => 'مفاجئ'])
@if(auth()->user()->hasPermission('treasury.cash_sessions.open'))
<form class="sw-card" method="POST" action="{{ route('treasury.cash-sessions.store') }}">@csrf
    <div class="sw-form-grid"><label>الخزينة<select name="cash_box_id" required><option value="">اختر الخزينة</option>@foreach($cashBoxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach</select></label><label>أمين الخزينة<select name="custodian_user_id" required><option value="">اختر الأمين</option>@foreach($custodians as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></label><label>تاريخ العمل<input name="business_date" type="date" value="{{ now()->toDateString() }}" required></label><label>ملاحظات الفتح<input name="opening_notes"></label></div><button class="sw-btn">فتح جلسة</button>
</form>
@endif
@foreach($sessions as $session)
<section class="sw-card"><h3>{{ $session->session_number }} — {{ $session->cashBox->name }} — {{ $session->status }}</h3><p>الرصيد الدفتري الافتتاحي: {{ $session->opening_book_balance }} | العد الافتتاحي: {{ $session->opening_counted_balance }} | الفرق: {{ $session->opening_difference }} | أمين الخزينة: {{ $session->custodian?->name ?? 'غير محدد' }}</p>
@if(in_array($session->status, ['opened','counting']) && auth()->user()->hasPermission('treasury.cash_sessions.count'))
<form method="POST" action="{{ route('treasury.cash-sessions.counts.store', $session) }}" data-count-form>@csrf
    <div class="sw-form-grid"><label>نوع العد<select name="count_type">@foreach($countTypes as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></label><label class="sw-check"><input type="checkbox" name="zero_count" value="1" data-zero-count><span class="sw-check__box" aria-hidden="true"></span><span>الخزينة فارغة — تسجيل عد صفري</span></label><label>الفئة النقدية<input name="lines[0][denomination]" type="number" step="0.01" min="0.01" data-denomination required></label><label>الكمية<input name="lines[0][quantity]" type="number" min="1" data-quantity required></label><label>ملاحظات<input name="notes" placeholder="ملاحظات العد الصفري اختيارية"></label></div><button class="sw-btn">تسجيل العد وإرساله للمراجعة</button>
</form>
@endif
@foreach($session->counts as $count)<div><p>{{ $countTypes[$count->count_type] ?? $count->count_type }}: {{ $count->counted_total }} | الرصيد الدفتري {{ $count->book_total }} | الفرق {{ $count->difference }} | {{ $count->status }}</p>@php($countActions = ['draft'=>['submit','count'],'submitted'=>['review','count'],'reviewed'=>['approve','approve']])@if($countAction = $countActions[$count->status] ?? null)@if(auth()->user()->hasPermission('treasury.cash_sessions.'.$countAction[1]))<form method="POST" action="{{ route('treasury.cash-counts.action', [$count, $countAction[0]]) }}" style="display:inline">@csrf<button class="sw-btn">{{ $countAction[0] }}</button></form>@endif @endif</div>@endforeach
@php($sessionActions = ['opened'=>['start_counting','count'],'counting'=>['submit','submit'],'pending_approval'=>['approve','approve'],'approved'=>['close','close'],'closed'=>['reopen','reopen']])@if($sessionAction = $sessionActions[$session->status] ?? null)@if(auth()->user()->hasPermission('treasury.cash_sessions.'.$sessionAction[1]))<form method="POST" action="{{ route('treasury.cash-sessions.action', [$session, $sessionAction[0]]) }}" style="display:inline">@csrf<button class="sw-btn">{{ $sessionAction[0] }}</button></form>@endif @endif
</section>
@endforeach
{{ $sessions->links() }}
<script>document.querySelectorAll('[data-count-form]').forEach(function(form){const toggle=form.querySelector('[data-zero-count]'),denomination=form.querySelector('[data-denomination]'),quantity=form.querySelector('[data-quantity]');toggle.addEventListener('change',function(){denomination.disabled=quantity.disabled=this.checked;denomination.required=quantity.required=!this.checked;if(this.checked)denomination.value=quantity.value='';});});</script>
@endsection
