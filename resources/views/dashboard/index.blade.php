@extends('layouts.app')

@section('title', 'لوحة التحكم')
@section('page-title', 'لوحة التحكم')
@section('page-description', 'نظرة عامة على حالة التشغيل اليوم.')

@section('content')
    <section class="sw-welcome-banner">
        <div>
            <span class="sw-eyebrow">صباح العمل</span>
            <h2>أهلًا، {{ auth()->user()->name }}</h2>
            <p>لوحة التحكم جاهزة. ستظهر مؤشرات العمل الفعلية عند تفعيل الموديولات في المراحل القادمة.</p>
        </div>
        <div class="sw-welcome-banner__meta">
            <x-icon name="clock" :size="20" />
            <span>{{ now()->locale('ar')->translatedFormat('l، j F Y') }}</span>
        </div>
    </section>

    <div class="sw-stats-grid">
        @foreach($statistics as $stat)
            <x-stat-card :label="$stat['label']" :value="$stat['value']" :hint="$stat['hint']" :icon="$stat['icon']" />
        @endforeach
    </div>

    <div class="sw-dashboard-grid">
        <x-card title="ملخص الأداء" subtitle="سيتم ربط الرسم بالبيانات الفعلية لاحقًا" class="sw-dashboard-grid__chart">
            <div class="sw-chart-placeholder" role="img" aria-label="الرسم البياني غير متاح بعد">
                <div class="sw-chart-placeholder__grid">
                    @for($i = 0; $i < 6; $i++)<span></span>@endfor
                </div>
                <svg viewBox="0 0 640 180" preserveAspectRatio="none" aria-hidden="true">
                    <path class="sw-chart-placeholder__area" d="M0 150 C80 142 95 105 165 118 S265 68 330 90 S430 45 500 64 S580 30 640 38 V180 H0Z"/>
                    <path class="sw-chart-placeholder__line" d="M0 150 C80 142 95 105 165 118 S265 68 330 90 S430 45 500 64 S580 30 640 38"/>
                </svg>
                <div class="sw-chart-placeholder__message">
                    <strong>بانتظار البيانات</strong>
                    <span>ستظهر اتجاهات الأداء هنا</span>
                </div>
            </div>
        </x-card>

        <x-card title="آخر الأنشطة" subtitle="سجل مختصر لأحدث العمليات">
            <x-empty-state title="لا توجد أنشطة بعد" message="ستظهر العمليات الأخيرة بعد تشغيل الموديولات." icon="clock" />
        </x-card>

        <x-card title="تنبيهات النظام" subtitle="الملاحظات التي تحتاج انتباهك">
            <div class="sw-notice-list">
                <div class="sw-notice">
                    <span class="sw-notice__icon"><x-icon name="info" :size="19" /></span>
                    <div><strong>مرحلة التأسيس</strong><p>الموديولات التشغيلية لم تُفعّل بعد.</p></div>
                </div>
                <div class="sw-notice sw-notice--muted">
                    <span class="sw-notice__icon"><x-icon name="bell" :size="19" /></span>
                    <div><strong>لا توجد تنبيهات</strong><p>كل شيء هادئ حاليًا.</p></div>
                </div>
            </div>
        </x-card>

        <x-card title="إجراءات سريعة" subtitle="ستتاح مع تفعيل صلاحيات وموديولات النظام">
            <div class="sw-quick-actions">
                <button type="button" disabled><x-icon name="clipboard" /> أمر عمل جديد <small>قريبًا</small></button>
                <button type="button" disabled><x-icon name="users" /> إضافة عميل <small>قريبًا</small></button>
                <button type="button" disabled><x-icon name="chart" /> عرض التقارير <small>قريبًا</small></button>
            </div>
        </x-card>
    </div>
@endsection
