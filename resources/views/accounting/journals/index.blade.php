@extends('layouts.app')
@section('title','القيود اليومية') @section('page-title','القيود اليومية')
@section('page-actions')
@if(auth()->user()->hasPermission('accounting.journals.create'))<a class="sw-btn" href="{{ route('accounting.journals.create') }}">قيد يدوي جديد</a>@endif
@endsection
@section('content')
<form class="sw-card sw-form" method="GET"><div class="sw-form-grid">
<label>الحالة<select name="status"><option value="">الكل</option>@foreach(['draft','pending_approval','approved','posted','reversed','cancelled'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></label>
<label>النوع<select name="entry_type"><option value="">الكل</option>@foreach(['manual','automatic','reversal'] as $type)<option @selected(request('entry_type')===$type)>{{ $type }}</option>@endforeach</select></label>
</div><button class="sw-btn">تصفية</button></form>
<div class="sw-card"><table class="sw-table"><thead><tr><th>الرقم</th><th>التاريخ</th><th>النوع</th><th>الحالة</th><th>مدين</th><th>دائن</th></tr></thead><tbody>
@forelse($entries as $entry)<tr><td><a href="{{ route('accounting.journals.show',$entry) }}">{{ $entry->journal_number }}</a></td><td>{{ $entry->entry_date->format('Y-m-d') }}</td><td>{{ $entry->entry_type }}</td><td>{{ $entry->status }}</td><td>{{ $entry->total_debit }}</td><td>{{ $entry->total_credit }}</td></tr>@empty<tr><td colspan="6">لا توجد قيود.</td></tr>@endforelse
</tbody></table>{{ $entries->links() }}</div>
@endsection
