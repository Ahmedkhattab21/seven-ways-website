@extends('layouts.app')
@section('title','الفترات المحاسبية') @section('page-title','الفترات المحاسبية')
@section('content')
<div class="sw-alert">إغلاق الفترات هنا Foundation فقط ولا ينشئ قيود إقفال.</div>
@if(auth()->user()->hasPermission('accounting.periods.create'))<form class="sw-card sw-form" method="POST" action="{{ route('accounting.periods.store') }}">@csrf<div class="sw-form-grid">
<label>السنة<select name="fiscal_year_id">@foreach($years as $year)<option value="{{ $year->id }}">{{ $year->name }}</option>@endforeach</select></label>
<label>الرقم<input type="number" min="1" name="period_number" required></label><label>الكود<input name="code" required></label><label>الاسم<input name="name" required></label>
<label>من<input type="date" name="start_date" required></label><label>إلى<input type="date" name="end_date" required></label>
<label><input type="hidden" name="is_adjustment_period" value="0"><input type="checkbox" name="is_adjustment_period" value="1"> فترة تسويات</label>
</div><button class="sw-btn">إنشاء فترة</button></form>@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>السنة</th><th>الرقم</th><th>الكود</th><th>الفترة</th><th>الحالة</th><th>تسويات</th></tr></thead><tbody>
@foreach($periods as $period)<tr><td>{{ $period->fiscalYear->name }}</td><td>{{ $period->period_number }}</td><td>{{ $period->code }}</td><td>{{ $period->start_date->toDateString() }} — {{ $period->end_date->toDateString() }}</td><td>{{ $period->status }}</td><td>{{ $period->is_adjustment_period?'نعم':'لا' }}</td></tr>@endforeach
</tbody></table>{{ $periods->links() }}</div>
@endsection
