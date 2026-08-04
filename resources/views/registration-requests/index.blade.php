@extends('layouts.app')

@section('title', 'الطلبات')
@section('page-title', 'الطلبات')
@section('breadcrumb', 'الرئيسية / الطلبات')
@section('page-description', 'طلبات التسجيل الواردة من الموقع الإلكتروني.')

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

    <x-table-shell title="طلبات التسجيل" description="كل طلبات العملاء المرسلة من نموذج التسجيل بالموقع.">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>رقم الهاتف</th>
                <th>الموقع</th>
                <th>الخدمة</th>
                <th>الفرع المفضل</th>
                <th>تاريخ الطلب</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($registrationRequests as $item)
                <tr>
                    <td>{{ $item->full_name }}</td>
                    <td><span dir="ltr">{{ $item->phone }}</span></td>
                    <td>{{ $countryLabels[$item->country] ?? $item->country }} — {{ $item->city }}</td>
                    <td>{{ $serviceLabels[$item->service] ?? $item->service }}</td>
                    <td>{{ data_get($branches->get($item->preferred_branch), 'name.ar', '—') }}</td>
                    <td><span dir="ltr">{{ $item->created_at->format('Y-m-d H:i') }}</span></td>
                    <td>
                        <a class="sw-button sw-button--secondary" href="{{ route('registration-requests.show', $item) }}">
                            عرض
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state title="لا توجد طلبات" message="ستظهر طلبات التسجيل الجديدة هنا تلقائيًا." icon="clipboard" />
                    </td>
                </tr>
            @endforelse
        </tbody>

        @if($registrationRequests->hasPages())
            <x-slot:footer>{{ $registrationRequests->links() }}</x-slot:footer>
        @endif
    </x-table-shell>
@endsection
