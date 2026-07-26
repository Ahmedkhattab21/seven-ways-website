@extends('layouts.app')
@section('title',$entry->journal_number) @section('page-title',$entry->journal_number)
@section('content')
<div class="sw-card"><p>{{ $entry->description }}</p><p>الحالة: {{ $entry->status }} | التاريخ: {{ $entry->entry_date->format('Y-m-d') }} | المصدر: {{ $entry->source_number ?: 'يدوي' }}</p>
<table class="sw-table"><thead><tr><th>#</th><th>الحساب</th><th>الوصف</th><th>مدين</th><th>دائن</th></tr></thead><tbody>
@foreach($entry->lines as $line)<tr><td>{{ $line->line_number }}</td><td>{{ $line->account->account_code }} — {{ $line->account->name_ar }}</td><td>{{ $line->description }}</td><td>{{ $line->debit_amount }}</td><td>{{ $line->credit_amount }}</td></tr>@endforeach
<tr><th colspan="3">الإجمالي</th><th>{{ $entry->total_debit }}</th><th>{{ $entry->total_credit }}</th></tr></tbody></table></div>
<div class="sw-card">@foreach(['submit','approve','post','cancel'] as $action)
@can($action,$entry)<form method="POST" action="{{ route('accounting.journals.action',[$entry,$action]) }}" style="display:inline">@csrf<button class="sw-btn">{{ $action }}</button></form>@endcan
@endforeach
@can('reverse',$entry) @if($entry->status==='posted')<form method="POST" action="{{ route('accounting.journals.reverse',$entry) }}">@csrf<label>سبب العكس<input name="reason" required></label><button class="sw-btn">Reverse</button></form>@endif @endcan</div>
@endsection
