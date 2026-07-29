@extends('layouts.app')
@section('title', $servicePackage->exists ? 'تعديل باقة خدمات' : 'إضافة باقة خدمات')
@section('page-title', $servicePackage->exists ? 'تعديل باقة خدمات' : 'إضافة باقة خدمات')
@section('breadcrumb', 'مركز المنتجات والخدمات / باقات الخدمات')
@section('content')
@php
    $packageItems = old('items', $servicePackage->exists
        ? $servicePackage->items->map(fn ($item) => ['service_id' => $item->service_id, 'quantity' => $item->quantity])->all()
        : [['service_id' => '', 'quantity' => 1]]);
    $canManagePrices = auth()->user()->hasPermission('service_packages.manage_prices');
@endphp
<x-catalog-navigation active="packages" />

<x-card title="بيانات الباقة وخدماتها">
    <form class="sw-form" method="POST"
          action="{{ $servicePackage->exists ? route('service-packages.update', $servicePackage) : route('service-packages.store') }}"
          data-package-form>
        @csrf
        @if($servicePackage->exists) @method('PUT') @endif

        <div class="sw-form-grid">
            <x-form.input name="code" label="الكود (تلقائي عند تركه فارغًا)" :value="old('code', $servicePackage->code)" />
            <x-form.input name="name" label="اسم الباقة" :value="old('name', $servicePackage->name)" required />
            <x-form.select name="package_type" label="نوع الباقة">
                @foreach(['fixed' => 'ثابتة', 'vehicle_size' => 'حسب حجم السيارة', 'custom' => 'مخصصة'] as $type => $label)
                    <option value="{{ $type }}" @selected(old('package_type', $servicePackage->package_type ?? 'fixed') === $type)>{{ $label }}</option>
                @endforeach
            </x-form.select>
            <x-form.input type="date" name="start_date" label="بداية إتاحة الباقة" :value="old('start_date', $servicePackage->start_date?->toDateString())" />
            <x-form.input type="date" name="end_date" label="نهاية إتاحة الباقة" :value="old('end_date', $servicePackage->end_date?->toDateString())" />
        </div>
        <x-form.textarea name="description" label="الوصف">{{ old('description', $servicePackage->description) }}</x-form.textarea>

        <section class="package-items-editor">
            <div class="quotation-section-heading">
                <div>
                    <h3>الخدمات داخل الباقة</h3>
                    <p>اختر خدمة واحدة على الأقل وحدد كمية كل خدمة.</p>
                </div>
                <button type="button" class="sw-button" data-add-package-service>+ إضافة خدمة أخرى</button>
            </div>
            <div data-package-items>
                @foreach($packageItems as $index => $item)
                    <div class="package-service-row" data-package-item>
                        <label>الخدمة
                            <select name="items[{{ $index }}][service_id]" required data-package-service>
                                <option value="">اختر الخدمة</option>
                                @foreach($services as $service)
                                    @php
                                        $priceMap = $service->branchServices->mapWithKeys(fn ($row) => [$row->branch_id => $row->default_price])
                                            ->merge($service->prices->groupBy('branch_id')->map(fn ($rows) => $rows->first()?->price));
                                    @endphp
                                    <option value="{{ $service->id }}"
                                            data-prices='@json($priceMap)'
                                            @selected(($item['service_id'] ?? null) == $service->id)>
                                        {{ $service->code }} — {{ $service->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label>الكمية
                            <input type="number" min="0.0001" step="0.0001" name="items[{{ $index }}][quantity]"
                                   value="{{ $item['quantity'] ?? 1 }}" required data-package-quantity>
                        </label>
                        <button type="button" class="sw-button sw-button--danger" data-remove-package-service>حذف الخدمة</button>
                    </div>
                @endforeach
            </div>
            <p class="sw-alert sw-alert--danger" data-package-duplicate hidden>لا يمكن تكرار نفس الخدمة داخل الباقة.</p>
        </section>

        @if(! $servicePackage->exists && $canManagePrices)
            <section class="package-pricing-editor">
                <h3>تسعير الباقة وإتاحتها</h3>
                <div class="sw-form-grid">
                    <x-form.select name="branch_id" label="الفرع">
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.select name="vehicle_size_id" label="حجم السيارة (اختياري)">
                        <option value="">كل الأحجام</option>
                        @foreach($vehicleSizes as $size)
                            <option value="{{ $size->id }}" @selected(old('vehicle_size_id') == $size->id)>{{ $size->name }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.input type="number" step="0.0001" min="0" name="price" label="سعر الباقة المعتمد" :value="old('price')" required />
                    <x-form.input type="number" step="0.0001" min="0" name="minimum_price" label="الحد الأدنى للسعر" :value="old('minimum_price')" />
                    <x-form.input type="date" name="effective_from" label="ساري من" :value="old('effective_from', now()->toDateString())" required />
                    <x-form.input type="date" name="effective_to" label="ساري حتى (اختياري)" :value="old('effective_to')" />
                </div>
                <input type="hidden" name="is_available" value="0">
                <x-form.checkbox name="is_available" label="متاحة في الفرع" :checked="old('is_available', true)" />
                <div class="package-pricing-summary">
                    <div><span>مجموع أسعار الخدمات منفردة</span><strong><span data-package-standalone>0.00</span> EGP</strong></div>
                    <div><span>سعر الباقة المقترح</span><strong><span data-package-suggested>0.00</span> EGP</strong></div>
                    <div><span>سعر الباقة المعتمد المدخل</span><strong><span data-package-approved>0.00</span> EGP</strong></div>
                    <div><span>قيمة التوفير</span><strong><span data-package-saving>0.00</span> EGP</strong></div>
                    <div><span>نسبة التوفير</span><strong><span data-package-saving-percent>0.00</span>%</strong></div>
                </div>
                <p class="sw-alert sw-alert--warning" data-package-missing-prices hidden>
                    بعض الخدمات المختارة ليس لها سعر فعال في الفرع المحدد؛ لذلك لا يمكن حساب مجموع أسعارها أو التوفير بدقة.
                    سعر الباقة المعتمد الذي تدخله سيُحفظ ويُستخدم في عرض السعر.
                </p>
            </section>
        @endif

        <section class="sw-card">
            <h3>إعدادات الضمان الافتراضية</h3>
            <input type="hidden" name="requires_warranty" value="0">
            <x-form.checkbox name="requires_warranty" label="الباقة تشمل ضمانًا" :checked="old('requires_warranty', $servicePackage->requires_warranty)" />
            <div class="sw-form-grid">
                <x-form.input name="default_warranty_film_type" label="نوع الفيلم" :value="old('default_warranty_film_type', $servicePackage->default_warranty_film_type)" />
                <x-form.input name="default_warranty_application_area" label="منطقة التطبيق" :value="old('default_warranty_application_area', $servicePackage->default_warranty_application_area)" />
                <x-form.input type="number" name="default_warranty_duration_value" label="مدة الضمان" :value="old('default_warranty_duration_value', $servicePackage->default_warranty_duration_value)" min="1" />
                <label>وحدة المدة<select class="sw-input" name="default_warranty_duration_unit"><option value="">اختر</option>@foreach(['days'=>'أيام','months'=>'شهور','years'=>'سنوات','lifetime'=>'مدى الحياة'] as $value=>$label)<option value="{{ $value }}" @selected(old('default_warranty_duration_unit', $servicePackage->default_warranty_duration_unit) === $value)>{{ $label }}</option>@endforeach</select></label>
                <x-form.textarea name="default_warranty_terms" label="شروط الضمان">{{ $servicePackage->default_warranty_terms }}</x-form.textarea>
                <x-form.textarea name="default_warranty_notes" label="ملاحظات الضمان">{{ $servicePackage->default_warranty_notes }}</x-form.textarea>
            </div>
        </section>
        <input type="hidden" name="is_active" value="0">
        <x-form.checkbox name="is_active" label="الباقة نشطة" :checked="old('is_active', $servicePackage->exists ? $servicePackage->is_active : true)" />
        <div class="sw-form-actions"><x-button type="submit">حفظ باقة الخدمات</x-button></div>
    </form>
</x-card>

@if($servicePackage->exists && $canManagePrices)
    <x-card title="أسعار الفروع والأحجام">
        <form method="POST" action="{{ route('service-packages.prices.store', $servicePackage) }}" class="sw-form">
            @csrf
            <div class="sw-form-grid">
                <x-form.select name="branch_id" label="الفرع">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</x-form.select>
                <x-form.select name="vehicle_size_id" label="حجم السيارة"><option value="">كل الأحجام</option>@foreach($vehicleSizes as $size)<option value="{{ $size->id }}">{{ $size->name }}</option>@endforeach</x-form.select>
                <x-form.input type="number" step="0.0001" name="price" label="السعر المعتمد" min="0" required />
                <x-form.input type="number" step="0.0001" name="minimum_price" label="الحد الأدنى" min="0" />
                <x-form.input type="date" name="effective_from" label="ساري من" :value="now()->toDateString()" required />
                <x-form.input type="date" name="effective_to" label="ساري حتى" />
            </div>
            <input type="hidden" name="is_available" value="1">
            <div class="sw-form-actions"><x-button type="submit">حفظ السعر</x-button></div>
        </form>
    </x-card>
@endif

<template data-package-item-template>
    <div class="package-service-row" data-package-item>
        <label>الخدمة
            <select name="items[__INDEX__][service_id]" required data-package-service>
                <option value="">اختر الخدمة</option>
                @foreach($services as $service)
                    @php
                        $priceMap = $service->branchServices->mapWithKeys(fn ($row) => [$row->branch_id => $row->default_price])
                            ->merge($service->prices->groupBy('branch_id')->map(fn ($rows) => $rows->first()?->price));
                    @endphp
                    <option value="{{ $service->id }}" data-prices='@json($priceMap)'>{{ $service->code }} — {{ $service->name }}</option>
                @endforeach
            </select>
        </label>
        <label>الكمية<input type="number" min="0.0001" step="0.0001" name="items[__INDEX__][quantity]" value="1" required data-package-quantity></label>
        <button type="button" class="sw-button sw-button--danger" data-remove-package-service>حذف الخدمة</button>
    </div>
</template>
@endsection
