@extends('layouts.app')
@section('title','الأستاذ العام') @section('page-title','الأستاذ العام')
@section('content')
@include('accounting.reports._filters',['allowExport'=>auth()->user()->hasPermission('accounting.general_ledger.export')])
@isset($summary)<div class="sw-stat-grid"><div class="sw-card">افتتاحي: {{ $summary['opening_net'] }}</div><div class="sw-card">مدين: {{ $summary['period_debit'] }}</div><div class="sw-card">دائن: {{ $summary['period_credit'] }}</div><div class="sw-card">ختامي: {{ $summary['closing_net'] }}</div></div>
<div class="sw-card"><table class="sw-table"><thead><tr><th>التاريخ</th><th>القيد</th><th>المصدر</th><th>البيان</th><th>مدين</th><th>دائن</th><th>الرصيد الجاري</th><th>الفرع</th><th>مركز التكلفة</th></tr></thead><tbody>
@foreach($lines as $line)<tr><td>{{ $line->posting_date }}</td><td><a href="{{ route('accounting.journals.show',$line->journal_entry_id) }}">{{ $line->journal_number }}</a></td><td>{{ $line->source_number }}</td><td>{{ $line->description }}</td><td>{{ $line->base_debit_amount }}</td><td>{{ $line->base_credit_amount }}</td><td>{{ $line->running_balance }}</td><td>{{ $line->branch_name }}</td><td>{{ $line->cost_center_name }}</td></tr>@endforeach
</tbody></table>{{ $lines->links() }}</div>@endisset
@endsection
