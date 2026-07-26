@extends('layouts.app')
@section('title','استعلام القيود') @section('page-title','استعلام القيود')
@section('content') @include('accounting.reports._filters')
<div class="sw-card"><table class="sw-table"><thead><tr><th>القيد</th><th>التاريخ</th><th>النوع</th><th>المصدر</th><th>الحالة</th><th>مدين</th><th>دائن</th></tr></thead><tbody>
@foreach($entries as $entry)<tr><td><a href="{{ route('accounting.journals.show',$entry) }}">{{ $entry->journal_number }}</a></td><td>{{ $entry->posting_date }}</td><td>{{ $entry->entry_type }}</td><td>{{ $entry->source_number }}</td><td>{{ $entry->status }}</td><td>{{ $entry->total_debit }}</td><td>{{ $entry->total_credit }}</td></tr>@endforeach
</tbody></table>{{ $entries->links() }}</div>@endsection
