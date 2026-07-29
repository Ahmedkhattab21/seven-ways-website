@extends('layouts.app')
@section('title', 'جلسات الخزائن والجرد')
@section('page-title', 'جلسات الخزائن والجرد النقدي')
@section('content')
@php
    $countTypes = ['opening' => 'افتتاحي', 'interim' => 'مرحلي', 'closing' => 'ختامي', 'surprise' => 'مفاجئ'];
    $sessionStatuses = [
        'opened' => 'في انتظار العد الافتتاحي',
        'counting' => 'الجلسة نشطة',
        'pending_approval' => 'في انتظار اعتماد الجلسة',
        'approved' => 'الجلسة معتمدة',
        'closed' => 'الجلسة مغلقة',
        'cancelled' => 'ملغية',
    ];
    $countStatuses = [
        'draft' => 'مسودة',
        'submitted' => 'مُرسل للمراجعة',
        'reviewed' => 'تمت المراجعة',
        'approved' => 'معتمد',
        'cancelled' => 'ملغي',
    ];
@endphp
@if($errors->has('business'))<div class="sw-alert sw-alert--error">{{ $errors->first('business') }}</div>@endif
<div class="cash-sessions-page">
@if(auth()->user()->hasPermission('treasury.cash_sessions.open'))
<form class="sw-card cash-session-open-card" method="POST" action="{{ route('treasury.cash-sessions.store') }}">@csrf
    <div class="sw-form-grid">
        <label class="sw-field"><span class="sw-field__label">الخزينة</span><select class="sw-input" name="cash_box_id" required><option value="">اختر الخزينة</option>@foreach($cashBoxes as $box)<option value="{{ $box->id }}">{{ $box->name }}</option>@endforeach</select></label>
        <label class="sw-field"><span class="sw-field__label">أمين الخزينة</span><select class="sw-input" name="custodian_user_id" required><option value="">اختر الأمين</option>@foreach($custodians as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></label>
        <label class="sw-field"><span class="sw-field__label">تاريخ العمل</span><input class="sw-input" name="business_date" type="date" value="{{ now()->toDateString() }}" required></label>
        <label class="sw-field"><span class="sw-field__label">ملاحظات الفتح</span><input class="sw-input" name="opening_notes"></label>
    </div>
    <div class="cash-session-actions"><button class="sw-button sw-button--primary">فتح جلسة</button></div>
</form>
@endif
@foreach($sessions as $session)
@php
    $activeCounts = $session->counts->where('status', '!=', 'cancelled');
    $hasOpening = $activeCounts->contains('count_type', 'opening');
    $hasClosing = $activeCounts->contains('count_type', 'closing');
    $approvedClosing = $activeCounts->contains(fn ($count) => $count->count_type === 'closing' && $count->status === 'approved');
    $availableCountTypes = $session->status === 'opened' && ! $hasOpening
        ? ['opening' => $countTypes['opening']]
        : ($session->status === 'counting' && ! $hasClosing
            ? collect($countTypes)->only(['interim', 'closing', 'surprise'])->all()
            : []);
@endphp
<section class="sw-card cash-session-card">
    <header class="cash-session-card__header">
        <h3>{{ $session->session_number }} — {{ $session->cashBox->name }}</h3>
        <span class="cash-session-status">{{ $sessionStatuses[$session->status] ?? 'حالة غير معروفة' }}</span>
    </header>
    <div class="cash-session-summary">
        <div class="cash-session-stat"><span>الرصيد الدفتري الافتتاحي</span><strong>{{ $session->opening_book_balance }}</strong></div>
        <div class="cash-session-stat"><span>العد الافتتاحي</span><strong>{{ $session->opening_counted_balance }}</strong></div>
        <div class="cash-session-stat"><span>الفرق</span><strong>{{ $session->opening_difference }}</strong></div>
        <div class="cash-session-stat"><span>أمين الخزينة</span><strong>{{ $session->custodian?->name ?? 'غير محدد' }}</strong></div>
    </div>

    @if($availableCountTypes !== [] && auth()->user()->hasPermission('treasury.cash_sessions.count'))
    <form method="POST" action="{{ route('treasury.cash-sessions.counts.store', $session) }}" class="cash-session-count-form">@csrf
        <p class="cash-session-reference">الرصيد الدفتري المرجعي: <strong>{{ $session->status === 'opened' ? $session->opening_book_balance : 'يُحسب لحظة التسجيل' }}</strong></p>
        <div class="sw-form-grid">
            <label class="sw-field"><span class="sw-field__label">نوع العد</span><select class="sw-input" name="count_type" required>@foreach($availableCountTypes as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></label>
            <label class="sw-field"><span class="sw-field__label">طريقة العد</span><select class="sw-input" name="count_input_mode" required data-count-mode><option value="match_book">مطابق للرصيد الدفتري</option><option value="manual_total">الرصيد الفعلي مختلف</option><option value="empty">الخزينة فارغة</option></select></label>
            <label class="sw-field" data-manual-total hidden><span class="sw-field__label">إجمالي النقدية المعدودة فعليًا</span><input class="sw-input" name="counted_total" type="number" min="0.01" step="0.0001" data-counted-total></label>
            <label class="sw-field"><span class="sw-field__label">ملاحظات</span><input class="sw-input" name="notes"></label>
        </div>
        <div class="cash-session-actions"><button class="sw-button sw-button--primary">تسجيل العد وإرساله للمراجعة</button></div>
    </form>
    @endif

    <div class="cash-session-count-list">
    @foreach($session->counts as $count)
    @php
        $difference = (float) $count->difference;
        $differenceLabel = $difference === 0.0 ? 'مطابق' : ($difference < 0 ? 'عجز بقيمة '.number_format(abs($difference), 4) : 'زيادة بقيمة '.number_format($difference, 4));
        $countActions = ['submitted' => ['review', 'review', 'مراجعة العد'], 'reviewed' => ['approve', 'approve', 'اعتماد العد']];
        $countAction = $countActions[$count->status] ?? null;
    @endphp
    <div class="cash-session-count-card">
        <div class="cash-session-count-card__header">
            <strong>{{ $countTypes[$count->count_type] ?? 'نوع غير معروف' }}</strong>
            <span>{{ $countStatuses[$count->status] ?? 'حالة غير معروفة' }}</span>
        </div>
        <div class="cash-session-summary cash-session-summary--count">
            <div class="cash-session-stat"><span>الرصيد الدفتري</span><strong>{{ $count->book_total }}</strong></div>
            <div class="cash-session-stat"><span>المبلغ المعدود</span><strong>{{ $count->counted_total }}</strong></div>
            <div class="cash-session-stat"><span>الفرق</span><strong>{{ $count->difference }}</strong></div>
            <div class="cash-session-stat"><span>النتيجة</span><strong>{{ $differenceLabel }}</strong></div>
        </div>
        @if($count->lines->isNotEmpty())
        <details><summary>تفاصيل الفئات النقدية القديمة</summary>@foreach($count->lines as $line)<p>{{ $line->denomination }} × {{ $line->quantity }} = {{ $line->line_total }}</p>@endforeach</details>
        @endif
        @if($countAction && auth()->user()->hasPermission('treasury.cash_sessions.'.$countAction[1]))
        <form method="POST" action="{{ route('treasury.cash-counts.action', [$count, $countAction[0]]) }}">@csrf<button class="sw-button sw-button--primary">{{ $countAction[2] }}</button></form>
        @endif
    </div>
    @endforeach
    </div>

    @if(in_array($session->status, ['counting', 'approved'], true) && ! $approvedClosing)
    <p class="sw-alert sw-alert--warning">يجب تسجيل ومراجعة واعتماد العد الختامي أولًا قبل إرسال الجلسة أو إغلاقها.</p>
    @endif
    @if($session->status === 'counting' && $approvedClosing && auth()->user()->hasPermission('treasury.cash_sessions.submit'))
    <form method="POST" action="{{ route('treasury.cash-sessions.action', [$session, 'submit']) }}">@csrf<button class="sw-button sw-button--primary">إرسال الجلسة للاعتماد</button></form>
    @elseif($session->status === 'pending_approval' && $approvedClosing && auth()->user()->hasPermission('treasury.cash_sessions.approve'))
    <form method="POST" action="{{ route('treasury.cash-sessions.action', [$session, 'approve']) }}">@csrf<button class="sw-button sw-button--primary">اعتماد الجلسة</button></form>
    @elseif($session->status === 'approved' && $approvedClosing && auth()->user()->hasPermission('treasury.cash_sessions.close'))
    <form method="POST" action="{{ route('treasury.cash-sessions.action', [$session, 'close']) }}" onsubmit="return confirm('هل أنت متأكد من إغلاق الجلسة؟ لن يمكن إضافة حركات نقدية جديدة بعد الإغلاق.')">@csrf<button class="sw-button sw-button--primary">إغلاق الجلسة</button></form>
    @endif
</section>
@endforeach
{{ $sessions->links() }}
</div>
<script>document.querySelectorAll('[data-count-mode]').forEach(function(mode){const form=mode.closest('form'),manual=form.querySelector('[data-manual-total]'),input=form.querySelector('[data-counted-total]');function sync(){const active=mode.value==='manual_total';manual.hidden=!active;input.required=active;input.disabled=!active;if(!active)input.value='';}mode.addEventListener('change',sync);sync();});</script>
@endsection
