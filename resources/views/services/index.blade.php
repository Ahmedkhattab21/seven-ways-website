@extends('layouts.app')
@section('title', 'الخدمات')
@section('page-title', 'كتالوج الخدمات')
@section('breadcrumb', 'الخدمات / القائمة')
@section('page-actions')
@if(auth()->user()->hasPermission('services.create'))<a class="sw-button sw-button--primary" href="{{ route('services.create') }}">إضافة خدمة</a>@endif
@endsection
@section('content')
<x-catalog-navigation active="services" />
<x-card title="البحث والفلاتر"><form method="GET" class="sw-form"><div class="sw-form-grid">
    <x-form.input name="search" label="الاسم أو الكود" :value="request('search')" />
    <x-form.select name="service_category_id" label="التصنيف"><option value="">الكل</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('service_category_id')==$category->id)>{{ $category->name }}</option>@endforeach</x-form.select>
    <x-form.select name="service_type" label="النوع"><option value="">الكل</option>@foreach(['ppf','thermal_insulation','tint','glass_protection','interior_protection','detailing','removal','maintenance','other'] as $type)<option value="{{ $type }}" @selected(request('service_type')===$type)>{{ $type }}</option>@endforeach</x-form.select>
    <x-form.select name="pricing_type" label="التسعير"><option value="">الكل</option>@foreach(['fixed','by_vehicle_size','by_vehicle_type','custom_quote','per_unit'] as $type)<option value="{{ $type }}" @selected(request('pricing_type')===$type)>{{ $type }}</option>@endforeach</x-form.select>
    <x-form.select name="branch_id" label="الفرع"><option value="">الفرع الحالي</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($currentBranchId==$branch->id)>{{ $branch->name }}</option>@endforeach</x-form.select>
    <x-form.select name="status" label="الحالة"><option value="">الكل</option><option value="active" @selected(request('status')==='active')>نشط</option><option value="inactive" @selected(request('status')==='inactive')>معطل</option></x-form.select>
</div><div class="sw-form-actions"><x-button type="submit">تطبيق</x-button></div></form></x-card>
<x-table-shell>
<thead><tr><th>الكود</th><th>الخدمة</th><th>التصنيف</th><th>السعر الساري</th><th>المصدر</th><th>الحد الأدنى</th><th>المدة</th><th>المواد</th><th>متاحة</th><th>الحالة</th><th></th></tr></thead>
<tbody>@forelse($services as $service)<tr>
    @php($availability = $service->branchServices->first())
    @php($currentPrice = $service->prices->first())
    <td>{{ $service->code }}</td><td><a href="{{ route('services.show', $service) }}">{{ $service->name }}</a></td>
    <td>{{ $service->category?->name }}</td>
    <td>{{ ($currentPrice?->price ?? $availability?->default_price) !== null ? number_format((float) ($currentPrice?->price ?? $availability?->default_price), 2) : 'غير مسعّرة' }}</td>
    <td>{{ $currentPrice ? 'سعر فرع ساري' : ($availability?->default_price !== null ? 'افتراضي الفرع' : '—') }}</td>
    <td>{{ $currentPrice?->minimum_price ?? $availability?->minimum_price ?? '—' }}</td>
    <td>{{ $currentPrice?->estimated_duration_minutes ?? $availability?->default_duration_minutes ?? $service->default_duration_minutes ?? '—' }}</td>
    <td>{{ $service->material_requirements_count }}</td><td>{{ $availability?->is_available ? 'نعم' : 'لا' }}</td>
    <td><x-status-badge :status="$service->is_active ? 'active' : 'inactive'" /></td>
    <td>@if(auth()->user()->hasPermission('services.update'))<a href="{{ route('services.edit', $service) }}">تعديل</a>@endif</td>
</tr>@empty<tr><td colspan="11">لا توجد خدمات.</td></tr>@endforelse</tbody>
<x-slot:footer>{{ $services->links() }}</x-slot:footer>
</x-table-shell>
@endsection
