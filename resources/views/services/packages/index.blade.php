@extends('layouts.app')
@section('title', 'باقات الخدمات')
@section('page-title', 'باقات الخدمات')
@section('breadcrumb', 'مركز المنتجات والخدمات / باقات الخدمات')
@section('page-actions')
@if(auth()->user()->hasPermission('service_packages.create'))
    <a class="sw-button sw-button--primary" href="{{ route('service-packages.create') }}">+ إضافة باقة خدمات</a>
@endif
@endsection
@section('content')
<x-catalog-navigation active="packages" />
<x-card title="البحث والفلاتر">
    <form method="GET" class="sw-form">
        <div class="sw-form-grid">
            <x-form.input name="search" label="بحث" :value="request('search')" placeholder="الاسم أو الكود" />
            <x-form.select name="branch_id" label="الفرع">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($currentBranchId==$branch->id)>{{ $branch->name }}</option>@endforeach</x-form.select>
            <x-form.select name="status" label="الحالة"><option value="">الكل</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="inactive" @selected(request('status')==='inactive')>معطل</option></x-form.select>
        </div>
        <div class="sw-form-actions"><x-button type="submit">تطبيق</x-button></div>
    </form>
</x-card>
<x-table-shell>
    <thead><tr>
        <th>الكود / الباقة</th><th>الخدمات والكميات</th><th>الفرع / الحجم</th><th>سعر الباقة</th>
        <th>الأسعار منفردة / التوفير</th><th>الحد الأدنى</th><th>المدة</th><th>فترة السعر</th>
        <th>الإتاحة</th><th>الحالة</th><th>الإجراءات</th>
    </tr></thead>
    <tbody>
    @forelse($packages as $package)
        @php
            $price = $package->branchPrices->first();
            $saving = $price && $package->standalone_total !== null
                ? max(0, (float) $package->standalone_total - (float) $price->price)
                : null;
        @endphp
        <tr>
            <td><strong>{{ $package->name }}</strong><small>{{ $package->code }}</small></td>
            <td>
                @foreach($package->items as $item)
                    <div>{{ $item->service?->name }} × {{ (float) $item->quantity }}</div>
                @endforeach
                <small>{{ $package->items_count }} خدمة</small>
            </td>
            <td>{{ $price?->branch?->name ?? '—' }}<small>{{ $price?->vehicleSize?->name ?? 'كل الأحجام' }}</small></td>
            <td>{{ $price ? number_format((float) $price->price, 2).' EGP' : 'غير مسعّرة' }}</td>
            <td>
                {{ $package->standalone_total !== null ? number_format((float) $package->standalone_total, 2).' EGP' : 'غير متاح' }}
                <small>التوفير: {{ $saving !== null ? number_format($saving, 2).' EGP' : '—' }}</small>
            </td>
            <td>{{ $price?->minimum_price !== null ? number_format((float) $price->minimum_price, 2) : '—' }}</td>
            <td>{{ number_format((float) $package->total_duration_minutes, 0) }} دقيقة</td>
            <td>{{ $price?->effective_from?->toDateString() ?? '—' }}<small>{{ $price?->effective_to?->toDateString() ?? 'مفتوح' }}</small></td>
            <td>{{ $price?->is_available ? 'متاحة' : 'غير متاحة' }}</td>
            <td><x-status-badge :status="$package->is_active ? 'active' : 'inactive'" /></td>
            <td>
                @if(auth()->user()->hasPermission('service_packages.update'))
                    <a href="{{ route('service-packages.edit', $package) }}">تعديل</a>
                @endif
                @if($package->is_active && auth()->user()->hasPermission('service_packages.disable'))
                    <form method="POST" action="{{ route('service-packages.disable', $package) }}" class="sw-inline-form">@csrf @method('PATCH')<button type="submit">تعطيل</button></form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="11">
            لا توجد باقات خدمات حتى الآن.
            @if(auth()->user()->hasPermission('service_packages.create'))
                <a href="{{ route('service-packages.create') }}">إضافة أول باقة خدمات</a>
            @endif
        </td></tr>
    @endforelse
    </tbody>
    <x-slot:footer>{{ $packages->links() }}</x-slot:footer>
</x-table-shell>
@endsection
