@extends('layouts.app')

@section('title', 'تفاصيل الطلب')
@section('page-title', 'تفاصيل الطلب')
@section('breadcrumb', 'الرئيسية / الطلبات / تفاصيل الطلب')
@section('page-description', 'بيانات العميل والسيارة والخدمة المطلوبة.')

@section('page-actions')
    <a class="sw-button sw-button--secondary" href="{{ route('registration-requests.index') }}">العودة إلى الطلبات</a>
@endsection

@section('content')
    @php
        $countryLabels = ['egypt' => 'مصر', 'saudi_arabia' => 'السعودية'];
        $serviceLabels = [
            'ppf' => 'أفلام حماية الطلاء',
            'thermal' => 'العزل الحراري',
            'nano' => 'نانو سيراميك',
            'polishing' => 'التلميع',
            'other' => 'أخرى',
        ];
    @endphp

    <x-table-shell title="طلب رقم {{ $registrationRequest->id }}">
        <tbody>
            <tr><th>الاسم بالكامل</th><td>{{ $registrationRequest->full_name }}</td></tr>
            <tr><th>رقم الهاتف</th><td><span dir="ltr">{{ $registrationRequest->phone }}</span></td></tr>
            <tr><th>البريد الإلكتروني</th><td>{{ $registrationRequest->email ?: '—' }}</td></tr>
            <tr><th>الدولة</th><td>{{ $countryLabels[$registrationRequest->country] ?? $registrationRequest->country }}</td></tr>
            <tr><th>المدينة</th><td>{{ $registrationRequest->city }}</td></tr>
            <tr><th>نوع السيارة</th><td>{{ $registrationRequest->vehicle_type }}</td></tr>
            <tr><th>موديل السيارة</th><td>{{ $registrationRequest->vehicle_model }}</td></tr>
            <tr><th>سنة الصنع</th><td>{{ $registrationRequest->vehicle_year ?: '—' }}</td></tr>
            <tr><th>الخدمة المطلوبة</th><td>{{ $serviceLabels[$registrationRequest->service] ?? $registrationRequest->service }}</td></tr>
            <tr><th>الفرع المفضل</th><td>{{ data_get($branches->get($registrationRequest->preferred_branch), 'name.ar', '—') }}</td></tr>
            <tr><th>ملاحظات</th><td>{{ $registrationRequest->notes ?: '—' }}</td></tr>
            <tr><th>تاريخ الطلب</th><td><span dir="ltr">{{ $registrationRequest->created_at->format('Y-m-d H:i') }}</span></td></tr>
        </tbody>
    </x-table-shell>
@endsection
