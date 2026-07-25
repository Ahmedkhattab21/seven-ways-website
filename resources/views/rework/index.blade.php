@extends('layouts.app')
@section('title', 'إعادة العمل')
@section('breadcrumb', 'التشغيل')
@section('page-title', 'إعادة العمل')
@section('content')
<div class="sw-card sw-table-wrap"><table class="sw-table"><thead><tr><th>الرقم</th><th>أمر العمل</th><th>الحالة</th><th>السبب</th><th></th></tr></thead><tbody>
@foreach($reworks as $rework)<tr><td>{{ $rework->rework_number }}</td><td>{{ $rework->workOrder->work_order_number }}</td><td>{{ $rework->status }}</td><td>{{ $rework->reason }}</td><td><a href="{{ route('rework-orders.show', $rework) }}">عرض</a></td></tr>@endforeach
</tbody></table>{{ $reworks->links() }}</div>
@endsection
