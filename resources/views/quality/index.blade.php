@extends('layouts.app')
@section('title', 'فحص الجودة')
@section('breadcrumb', 'الجودة')
@section('page-title', 'فحص الجودة')
@section('content')
<div class="sw-card">
    <div class="sw-page-actions">
        @if(auth()->user()->hasPermission('quality_checks.manage_templates'))
            <a class="sw-btn" href="{{ route('quality-templates.index') }}">قوالب الجودة</a>
        @endif
    </div>
</div>
<div class="sw-card sw-table-wrap">
    <h2>بانتظار الجودة</h2>
    <table class="sw-table">
        <thead><tr><th>أمر العمل</th><th>العميل</th><th>السيارة</th><th>إجراء</th></tr></thead>
        <tbody>
        @forelse($waitingOrders as $order)
            <tr>
                <td>{{ $order->work_order_number }}</td>
                <td>{{ $order->customer->name }}</td>
                <td>{{ $order->vehicle->plate_number ?: $order->vehicle->vin }}</td>
                <td>
                    @if(auth()->user()->hasPermission('quality_checks.create'))
                        <form method="POST" action="{{ route('quality-checks.start', $order) }}">@csrf<button class="sw-btn">بدء الفحص</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4">لا توجد أوامر بانتظار الجودة.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="sw-card sw-table-wrap">
    <h2>جولات الجودة</h2>
    <table class="sw-table">
        <thead><tr><th>الرقم</th><th>أمر العمل</th><th>الجولة</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @foreach($checks as $check)
            <tr><td>{{ $check->quality_check_number }}</td><td>{{ $check->workOrder->work_order_number }}</td><td>{{ $check->round_number }}</td><td>{{ $check->status }}</td><td><a href="{{ route('quality-checks.show', $check) }}">عرض</a></td></tr>
        @endforeach
        </tbody>
    </table>
    {{ $checks->links() }}
</div>
@endsection
