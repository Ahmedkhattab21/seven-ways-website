@extends('layouts.app')

@section('title', 'تقويم الحجوزات')

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
    $appointmentsByDate = $appointments->groupBy(
        fn ($appointment) => $appointment->scheduled_start->format('Y-m-d')
    );
@endphp

<div class="appointments-calendar-page">
    <div class="sw-page-header appointments-calendar-header">
        <div>
            <p class="appointments-calendar-header__eyebrow">التشغيل اليومي</p>
            <h1>تقويم الحجوزات</h1>
            <p>استعرض المواعيد حسب الفترة والفرع بصورة مرتبة وواضحة.</p>
        </div>
        <a class="sw-button sw-button--outline" href="{{ route('appointments.index') }}">عرض القائمة</a>
    </div>

    <x-card title="الفترة والفرع" subtitle="اختر نطاق التاريخ المطلوب ثم اضغط عرض المواعيد.">
        <form method="GET" class="sw-form appointments-calendar-filter">
            <div class="sw-form-grid appointments-calendar-filter__grid">
                <x-form.input
                    name="from"
                    type="date"
                    label="من تاريخ"
                    :value="request('from', today()->startOfMonth()->format('Y-m-d'))"
                    required
                />
                <x-form.input
                    name="to"
                    type="date"
                    label="إلى تاريخ"
                    :value="request('to', today()->endOfMonth()->format('Y-m-d'))"
                    required
                />
                <x-form.select name="branch_id" label="الفرع">
                    <option value="">كل الفروع</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div class="sw-form-actions">
                <x-button type="submit">عرض المواعيد</x-button>
                <a class="sw-button sw-button--outline" href="{{ route('appointments.calendar') }}">الشهر الحالي</a>
            </div>
        </form>
    </x-card>

    <x-card title="المواعيد" :subtitle="$appointments->count().' موعد في الفترة المحددة'">
        @forelse($appointmentsByDate as $date => $rows)
            <section class="appointments-calendar-day">
                <header class="appointments-calendar-day__header">
                    <div>
                        <span class="appointments-calendar-day__number">{{ \Carbon\Carbon::parse($date)->format('d') }}</span>
                        <div>
                            <h3>{{ \Carbon\Carbon::parse($date)->translatedFormat('l') }}</h3>
                            <p>{{ $date }}</p>
                        </div>
                    </div>
                    <span>{{ $rows->count() }} موعد</span>
                </header>

                <div class="appointments-calendar-events">
                    @foreach($rows as $appointment)
                        <a class="appointments-calendar-event" href="{{ route('appointments.show', $appointment) }}">
                            <time>{{ $appointment->scheduled_start->format('H:i') }}</time>
                            <div>
                                <strong>{{ $appointment->customer->name }}</strong>
                                <span>{{ $appointment->appointment_number }} · {{ $appointment->branch->name }}</span>
                            </div>
                            <span class="sw-badge sw-badge--{{ $appointment->status }}">
                                <span class="sw-badge__dot"></span>
                                {{ $statusLabels[$appointment->status] ?? $appointment->status }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="appointments-calendar-empty">
                <strong>لا توجد مواعيد في الفترة المحددة.</strong>
                <span>غيّر الفترة أو الفرع، أو ارجع للشهر الحالي.</span>
            </div>
        @endforelse
    </x-card>
</div>
@endsection
