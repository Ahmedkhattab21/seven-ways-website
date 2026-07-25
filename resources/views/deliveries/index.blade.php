@extends('layouts.app')
@section('title', 'تسليم السيارات')
@section('breadcrumb', 'التشغيل')
@section('page-title', 'السيارات الجاهزة للتسليم')
@section('content')
<div class="sw-card sw-table-wrap"><table class="sw-table"><thead><tr><th>أمر العمل</th><th>العميل</th><th>السيارة</th><th></th></tr></thead><tbody>
@forelse($orders as $order)<tr><td>{{ $order->work_order_number }}</td><td>{{ $order->customer->name }}</td><td>{{ $order->vehicle->plate_number }}</td><td><a class="sw-btn" href="{{ route('deliveries.show', $order) }}">فحص وتسليم</a></td></tr>@empty<tr><td colspan="4">لا توجد سيارات جاهزة.</td></tr>@endforelse
</tbody></table>{{ $orders->links() }}</div>
@endsection
