@extends('layouts.app')
@section('title','الإقفال المحاسبي') @section('page-title','الإقفال المحاسبي')
@section('content')
<div class="sw-grid sw-grid-3"><div class="sw-card"><h3>السنة الحالية</h3><p>{{ $currentYear?->name ?? 'لا توجد' }}</p></div><div class="sw-card"><h3>استثناءات مانعة</h3><p>{{ $blockingExceptions }}</p></div><div class="sw-card"><h3>عكوس مجدولة</h3><p>{{ $scheduledReversals }}</p></div></div>
<div class="sw-card"><h3>الفترات</h3><table class="sw-table"><thead><tr><th>الفترة</th><th>الحالة</th><th>الموديولات المقفلة</th><th>الإجراء</th></tr></thead><tbody>
@foreach($periods as $period)<tr><td>{{ $period->code }} — {{ $period->name }}</td><td>{{ $period->status }}</td><td>{{ implode(', ', $period->locked_modules ?? []) }}</td><td>
@if($period->status==='open' && auth()->user()->hasPermission('accounting.closing.start'))<form method="POST" action="{{ route('accounting.closing.periods.start',$period) }}">@csrf<input type="hidden" name="closing_type" value="period_soft_close"><input name="reason" required placeholder="سبب الإقفال"><button class="sw-btn">بدء Soft Close</button></form>@endif
@if($period->status==='soft_closed' && auth()->user()->hasPermission('accounting.closing.start'))<form method="POST" action="{{ route('accounting.closing.periods.start',$period) }}">@csrf<input type="hidden" name="closing_type" value="period_hard_close"><input name="reason" required placeholder="سبب الإقفال"><button class="sw-btn">بدء Hard Close</button></form>@endif
</td></tr>@endforeach</tbody></table></div>
<div class="sw-card"><h3>Closing Runs</h3><table class="sw-table"><thead><tr><th>الرقم</th><th>النوع</th><th>الحالة</th><th>النتيجة</th></tr></thead><tbody>@foreach($runs as $run)<tr><td>{{ $run->run_number }}</td><td>{{ $run->closing_type }}</td><td>{{ $run->status }}</td><td>{{ count($run->validation_snapshot['blocking_errors'] ?? []) }} موانع</td></tr>@endforeach</tbody></table></div>
@endsection
