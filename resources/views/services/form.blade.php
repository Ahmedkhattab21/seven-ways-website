@extends('layouts.app')
@section('title', $service->exists ? 'تعديل خدمة' : 'إضافة خدمة')
@section('page-title', $service->exists ? 'تعديل خدمة' : 'إضافة خدمة')
@section('breadcrumb', 'الخدمات / البيانات الأساسية')
@section('content')
<x-catalog-navigation active="services" />
<x-card title="تعريف الخدمة على مستوى الشركة"><form class="sw-form" method="POST" action="{{ $service->exists ? route('services.update', $service) : route('services.store') }}">
@csrf @if($service->exists) @method('PUT') @endif
<div class="sw-form-grid">
<x-form.select name="branch_id" label="الفرع" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string) old('branch_id', $currentBranchId) === (string) $branch->id)>{{ $branch->name }}</option>@endforeach</x-form.select>
<x-form.input name="code" label="الكود (يُولد تلقائيًا عند تركه فارغًا)" :value="old('code', $service->code)" />
<x-form.input name="name" label="اسم الخدمة" :value="old('name', $service->name)" required />
<x-form.select name="service_category_id" label="التصنيف" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('service_category_id', $service->service_category_id)==$category->id)>{{ $category->name }}</option>@endforeach</x-form.select>
<x-form.select name="service_type" label="نوع الخدمة">@foreach(['ppf','thermal_insulation','tint','glass_protection','interior_protection','detailing','removal','maintenance','other'] as $type)<option value="{{ $type }}" @selected(old('service_type', $service->service_type)===$type)>{{ $type }}</option>@endforeach</x-form.select>
<x-form.select name="pricing_type" label="نوع التسعير">@foreach(['fixed','by_vehicle_size','by_vehicle_type','custom_quote','per_unit'] as $type)<option value="{{ $type }}" @selected(old('pricing_type', $service->pricing_type)===$type)>{{ $type }}</option>@endforeach</x-form.select>
<x-form.input type="number" name="default_duration_minutes" label="المدة الافتراضية بالدقائق" :value="old('default_duration_minutes', $service->default_duration_minutes ?? 60)" min="1" required />
<x-form.select name="default_tax_id" label="الضريبة الافتراضية"><option value="">بدون</option>@foreach($taxes as $tax)<option value="{{ $tax->id }}" @selected(old('default_tax_id', $service->default_tax_id)==$tax->id)>{{ $tax->name }} ({{ $tax->rate }}%)</option>@endforeach</x-form.select>
<x-form.select name="pricing_unit_id" label="وحدة التسعير للخدمة per_unit"><option value="">بدون</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(old('pricing_unit_id', $service->pricing_unit_id)==$unit->id)>{{ $unit->name }}</option>@endforeach</x-form.select>
<x-form.input type="number" name="default_warranty_months" label="أشهر الضمان المتوقعة" :value="old('default_warranty_months', $service->default_warranty_months)" min="0" />
<x-form.input name="short_description" label="وصف مختصر" :value="old('short_description', $service->short_description)" />
</div>
<x-form.textarea name="description" label="الوصف">{{ old('description', $service->description) }}</x-form.textarea>
<div class="sw-form-grid">@foreach(['requires_vehicle'=>'تتطلب سيارة','requires_inspection'=>'تتطلب فحصًا','requires_quality_check'=>'تتطلب فحص جودة','allows_multiple_technicians'=>'تسمح بعدة فنيين','is_package_only'=>'تباع داخل باقة فقط','is_active'=>'نشطة'] as $field=>$label)<div><input type="hidden" name="{{ $field }}" value="0"><x-form.checkbox :name="$field" :label="$label" :checked="old($field, $service->exists ? $service->{$field} : $field === 'requires_vehicle' || $field === 'is_active')" /></div>@endforeach</div>
<div class="sw-form-actions"><x-button type="submit">حفظ</x-button></div>
</form></x-card>
@endsection
