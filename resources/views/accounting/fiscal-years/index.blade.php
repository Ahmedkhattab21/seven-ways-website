@extends('layouts.app')
@section('title','السنوات المالية') @section('page-title','السنوات المالية')
@section('content')
@foreach($years as $year)
    @if($year->status === 'open' && $year->periods->where('is_adjustment_period', false)->isEmpty())
        <div class="sw-alert">السنة مفتوحة لكن فتراتها المحاسبية غير مكتملة. استخدم توليد الفترات ثم افتحها من جديد.</div>
    @endif
@endforeach
@if(auth()->user()->hasPermission('accounting.fiscal_years.create'))<form class="sw-card sw-form" method="POST" action="{{ route('accounting.fiscal-years.store') }}">@csrf<div class="sw-form-grid">
<label>الكود<input name="code" required></label><label>الاسم<input name="name" required></label>
<label>من<input type="date" name="start_date" required></label><label>إلى<input type="date" name="end_date" required></label>
</div><button class="sw-btn">إنشاء سنة</button></form>@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الكود</th><th>الاسم</th><th>من</th><th>إلى</th><th>الفترات</th><th>الحالة</th><th>الإجراءات</th></tr></thead><tbody>
@foreach($years as $year)<tr><td>{{ $year->code }}</td><td>{{ $year->name }}</td><td>{{ $year->start_date->toDateString() }}</td><td>{{ $year->end_date->toDateString() }}</td><td>{{ $year->periods->count() }}</td><td>{{ $year->status }}</td><td>
@if($year->status==='draft')<form method="POST" action="{{ route('accounting.fiscal-years.generate',$year) }}">@csrf<input type="hidden" name="frequency" value="monthly"><button class="sw-btn">توليد الفترات</button></form>
<form method="POST" action="{{ route('accounting.fiscal-years.action',[$year,'open']) }}">@csrf<button class="sw-btn">فتح</button></form>@endif
@if($year->status==='open')<form method="POST" action="{{ route('accounting.fiscal-years.action',[$year,'soft_close']) }}">@csrf<input name="reason" required placeholder="السبب"><button class="sw-btn">Soft Close</button></form>@endif
@if(in_array($year->status,['soft_closed','closed']))<form method="POST" action="{{ route('accounting.fiscal-years.action',[$year,'reopen']) }}">@csrf<input name="reason" required placeholder="سبب إعادة الفتح"><button class="sw-btn">إعادة فتح</button></form>@endif
</td></tr>@endforeach
</tbody></table></div>
@endsection
