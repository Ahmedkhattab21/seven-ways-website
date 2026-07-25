@extends('layouts.app')
@section('title','تقارير المشتريات') @section('page-title','تقارير المشتريات')
@section('content')
<div class="sw-card"><h2>{{ $report }}</h2><table class="sw-table"><thead><tr><th>المرجع</th><th>المورد</th><th>الحالة</th></tr></thead><tbody>@foreach($data as $document)<tr><td>{{ $document->purchase_order_number??$document->goods_receipt_number??$document->internal_invoice_number??$document->purchase_return_number }}</td><td>{{ $document->supplier->name }}</td><td>{{ $document->status }}</td></tr>@endforeach</tbody></table></div>
@endsection
