@extends('layouts.app')

@section('title', 'الحجوزات')

@section('content')
@php
    $statusLabels = [
        'pending' => 'في الانتظار',
        'confirmed' => 'مؤكد',
        'checked_in' => 'تم تسجيل الوصول',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
        'cancelled' => 'ملغي',
        'no_show' => 'لم يحضر',
    ];
    $depositLabels = [
        'not_required' => 'غير مطلوب',
        'pending' => 'في الانتظار',
        'partially_paid' => 'مدفوع جزئيًا',
        'paid' => 'مدفوع',
        'refunded' => 'مردود',
        'forfeited' => 'مصادر',
    ];
@endphp

<div class="appointments-index-page">
    <div class="sw-page-header appointments-index-header">
        <div>
            <p class="appointments-index-header__eyebrow">التشغيل اليومي</p>
            <h1>الحجوزات</h1>
            <p>جدولة ما قبل التنفيذ دون إنشاء أمر عمل أو حجز مخزون.</p>
        </div>
        <div class="sw-form-actions appointments-index-header__actions">
            <a class="sw-button sw-button--outline" href="{{ route('appointments.calendar') }}">عرض التقويم</a>
            @if(auth()->user()->hasPermission('appointments.create'))
                <a class="sw-button sw-button--primary" href="{{ route('appointments.create') }}">إضافة حجز</a>
            @endif
        </div>
    </div>

    <x-card title="البحث والتصفية" subtitle="حدد الحالة أو الفرع للوصول إلى الحجوزات المطلوبة.">
        <form method="GET" class="sw-form appointments-filter-form">
            <div class="sw-form-grid">
                <x-form.select name="status" label="الحالة">
                    <option value="">كل الحالات</option>
                    @foreach($statusLabels as $status => $label)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>
                    @endforeach
                </x-form.select>

                <x-form.select name="branch_id" label="الفرع">
                    <option value="">كل الفروع</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div class="sw-form-actions">
                <x-button type="submit">تطبيق الفلاتر</x-button>
                <a class="sw-button sw-button--outline" href="{{ route('appointments.index') }}">مسح</a>
            </div>
        </form>
    </x-card>

    <x-card title="قائمة الحجوزات" :subtitle="$appointments->total().' حجز مطابق'">
        <div class="sw-table-wrap">
            <table class="sw-table appointments-table">
                <thead>
                    <tr>
                        <th>الرقم</th>
                        <th>العميل</th>
                        <th>البداية</th>
                        <th>الفني</th>
                        <th>الحالة</th>
                        <th>العربون</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($appointments as $appointment)
                        <tr>
                            <td>
                                <a class="appointments-table__number" href="{{ route('appointments.show', $appointment) }}">
                                    {{ $appointment->appointment_number }}
                                </a>
                            </td>
                            <td>{{ $appointment->customer->name }}</td>
                            <td>{{ $appointment->scheduled_start->format('Y-m-d H:i') }}</td>
                            <td>{{ $appointment->assignedEmployee?->name ?? 'غير مسند' }}</td>
                            <td>
                                <span class="sw-badge sw-badge--{{ $appointment->status }}">
                                    <span class="sw-badge__dot"></span>
                                    {{ $statusLabels[$appointment->status] ?? $appointment->status }}
                                </span>
                            </td>
                            <td>{{ $depositLabels[$appointment->deposit_status] ?? $appointment->deposit_status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="appointments-empty-state">
                                    <strong>لا توجد حجوزات مطابقة.</strong>
                                    <span>غيّر الفلاتر أو أضف حجزًا جديدًا.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($appointments->hasPages())
            <div class="appointments-pagination">
                {{ $appointments->links() }}
            </div>
        @endif
    </x-card>
</div>
@endsection
