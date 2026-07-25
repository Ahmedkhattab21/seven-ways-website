@extends('layouts.app')
@section('title','أوامر العمل')
@section('breadcrumb','أوامر العمل')
@section('page-title','أوامر العمل')
@section('page-actions')
@if(auth()->user()->hasPermission('work_orders.create'))<a class="sw-btn sw-btn--primary" href="{{ route('work-orders.create') }}">أمر عمل جديد</a>@endif
@endsection
@section('content')
<form class="sw-filter-bar" method="GET">
    <select name="status"><option value="">كل الحالات</option>@foreach(['awaiting_inspection','inspection_completed','ready_to_start','in_progress','paused','awaiting_materials','awaiting_quality','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select>
    <select name="branch_id"><option value="">كل الفروع</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach</select>
    <select name="priority"><option value="">كل الأولويات</option>@foreach(['normal','high','urgent'] as $priority)<option value="{{ $priority }}">{{ $priority }}</option>@endforeach</select>
    <button class="sw-btn">تصفية</button>
</form>
<div class="sw-card sw-table-wrap"><table class="sw-table"><thead><tr><th>الرقم</th><th>العميل</th><th>السيارة</th><th>الفرع</th><th>الحالة</th><th>الأولوية</th></tr></thead><tbody>
@forelse($orders as $order)<tr><td><a href="{{ route('work-orders.show',$order) }}">{{ $order->work_order_number }}</a></td><td>{{ $order->customer->name }}</td><td>{{ $order->vehicle->plate_number ?: $order->vehicle->vin }}</td><td>{{ $order->branch->name }}</td><td>{{ $order->status }}</td><td>{{ $order->priority }}</td></tr>
@empty<tr><td colspan="6">لا توجد أوامر عمل.</td></tr>@endforelse
</tbody></table></div>{{ $orders->links() }}
@endsection
