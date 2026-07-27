@extends('layouts.app')
@section('title','قيود التسوية') @section('page-title','قيود التسوية')
@section('content')
<div class="sw-alert">قيود التسوية تمر بمراحل Draft → Submit → Approve → Post، ولا يمكن تعديل القيد المرحّل.</div>
<div class="sw-card"><table class="sw-table"><thead><tr><th>القيد</th><th>النوع</th><th>المرجع</th><th>الحالة</th><th>تاريخ العكس</th></tr></thead><tbody>@foreach($adjustments as $row)<tr><td>{{ $row->journalEntry->journal_number }}</td><td>{{ $row->adjustment_type }}</td><td>{{ $row->supporting_reference }}</td><td>{{ $row->status }}</td><td>{{ $row->scheduled_reversal_date?->toDateString() }}</td></tr>@endforeach</tbody></table>{{ $adjustments->links() }}</div>
@endsection
