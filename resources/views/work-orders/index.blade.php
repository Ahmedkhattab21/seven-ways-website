@extends('layouts.app')

@section('title', 'أوامر العمل')
@section('breadcrumb', 'أوامر العمل')
@section('page-title', 'أوامر العمل')

@section('content')
<div class="work-orders-index-page">
    <header class="work-orders-index-header">
        <div>
            <span class="work-orders-index-header__eyebrow">التشغيل اليومي</span>
            <h1>أوامر العمل</h1>
            <p>تابع أوامر العمل وحالة التنفيذ في كل فرع.</p>
        </div>

        @if(auth()->user()->hasPermission('work_orders.create'))
            <a class="sw-button sw-button--primary" href="{{ route('work-orders.create') }}">أمر عمل جديد</a>
        @endif
    </header>

    <section class="sw-card work-orders-filter-card">
        <div class="work-orders-filter-card__header">
            <div>
                <h2>البحث والتصفية</h2>
                <p>استخدم الفلاتر للوصول إلى أوامر العمل المطلوبة.</p>
            </div>
        </div>

        <form class="work-orders-filter-form" method="GET">
            <label>
                <span>الحالة</span>
                <select name="status">
                    <option value="">كل الحالات</option>
                    @foreach(['awaiting_inspection', 'inspection_completed', 'ready_to_start', 'in_progress', 'paused', 'awaiting_materials', 'awaiting_quality', 'cancelled'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>الفرع</span>
                <select name="branch_id">
                    <option value="">كل الفروع</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>الأولوية</span>
                <select name="priority">
                    <option value="">كل الأولويات</option>
                    @foreach(['normal', 'high', 'urgent'] as $priority)
                        <option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ $priority }}</option>
                    @endforeach
                </select>
            </label>

            <div class="work-orders-filter-form__actions">
                <button class="sw-button sw-button--primary">تطبيق الفلاتر</button>
                @if(request()->hasAny(['status', 'branch_id', 'priority']))
                    <a class="sw-button sw-button--outline" href="{{ route('work-orders.index') }}">مسح</a>
                @endif
            </div>
        </form>
    </section>

    <section class="sw-card work-orders-table-card">
        <div class="work-orders-table-card__header">
            <div>
                <h2>قائمة أوامر العمل</h2>
                <p>{{ $orders->total() }} أمر عمل</p>
            </div>
        </div>

        <div class="sw-table-scroll">
            <table class="sw-table">
                <thead>
                    <tr>
                        <th>الرقم</th>
                        <th>العميل</th>
                        <th>السيارة</th>
                        <th>الفرع</th>
                        <th>الحالة</th>
                        <th>الأولوية</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td>
                                <a class="work-orders-table-card__number" href="{{ route('work-orders.show', $order) }}">
                                    {{ $order->work_order_number }}
                                </a>
                            </td>
                            <td>{{ $order->customer->name }}</td>
                            <td>{{ $order->vehicle->plate_number ?: $order->vehicle->vin }}</td>
                            <td>{{ $order->branch->name }}</td>
                            <td><x-status-badge :status="$order->status" /></td>
                            <td><x-status-badge :status="$order->priority" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="work-orders-empty">
                                    <strong>لا توجد أوامر عمل.</strong>
                                    <span>جرّب تغيير الفلاتر أو أضف أمر عمل جديد.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="work-orders-pagination">
        {{ $orders->withQueryString()->links() }}
    </div>
</div>
@endsection
