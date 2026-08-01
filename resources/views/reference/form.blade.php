@extends('layouts.app')
@php($editing = $item->exists)
@section('title', ($editing ? 'تعديل ' : 'إضافة ').$config['title'])
@section('page-title', ($editing ? 'تعديل ' : 'إضافة ').$config['title'])
@section('breadcrumb', 'الإعدادات / '.$config['title'])
@section('content')
<x-card>
    <form method="POST" action="{{ $editing ? route('reference.update', [$section, $item->id]) : route('reference.store', $section) }}" class="sw-form">
        @csrf @if($editing) @method('PUT') @endif
        <div class="sw-form-grid">
            @if(in_array($section, ['currencies', 'vehicle-brands', 'vehicle-models']))
                @if($section === 'vehicle-models')
                    <x-form.select name="vehicle_brand_id" label="الماركة" required>
                        @foreach($brands as $brand)<option value="{{ $brand->id }}" @selected(old('vehicle_brand_id', $item->vehicle_brand_id) == $brand->id)>{{ $brand->name_ar }}</option>@endforeach
                    </x-form.select>
                @endif
                @if($section === 'currencies')<x-form.input name="code" label="الكود" :value="$item->code" required />@endif
                <x-form.input name="name_ar" label="الاسم العربي" :value="$item->name_ar" required />
                <x-form.input name="name_en" label="الاسم الإنجليزي" :value="$item->name_en" :required="$section === 'currencies'" />
                @if($section === 'currencies')
                    <x-form.input name="symbol" label="الرمز" :value="$item->symbol" required />
                    <x-form.input name="decimal_places" type="number" label="الخانات العشرية" :value="$item->decimal_places ?? 2" min="0" max="6" required />
                @elseif($section === 'vehicle-brands')
                    <x-form.input name="country_code" label="كود الدولة" :value="$item->country_code" />
                @else
                    <x-form.input name="start_year" type="number" label="من سنة" :value="$item->start_year" min="1900" max="2200" />
                    <x-form.input name="end_year" type="number" label="إلى سنة" :value="$item->end_year" min="1900" max="2200" />
                @endif
            @elseif($section === 'fiscal-years')
                <x-form.input name="name" label="اسم السنة" :value="$item->name" required />
                <x-form.input name="start_date" type="date" label="تاريخ البداية" :value="$item->start_date?->format('Y-m-d')" required />
                <x-form.input name="end_date" type="date" label="تاريخ النهاية" :value="$item->end_date?->format('Y-m-d')" required />
                <x-form.select name="status" label="الحالة" required>
                    @foreach(['open' => 'مفتوحة', 'locked' => 'مقفلة مؤقتًا', 'closed' => 'مغلقة'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $item->status ?? 'open') === $value)>{{ $label }}</option>@endforeach
                </x-form.select>
            @elseif($section === 'document-sequences')
                <x-form.select name="branch_id" label="النطاق">
                    <option value="">كل الشركة</option>
                    @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $item->branch_id) == $branch->id)>{{ $branch->code }} — {{ $branch->name }}</option>@endforeach
                </x-form.select>
                <x-form.select name="document_type" label="نوع المستند" required>
                    @foreach($documentTypes as $type => $definition)
                        <option value="{{ $type }}" @selected(old('document_type', $item->document_type) === $type)>
                            {{ $type }} — {{ $definition['label'] }}
                        </option>
                    @endforeach
                </x-form.select>
                <x-form.input name="prefix" label="القالب" :value="$item->prefix" help="مثال: {BRANCH}-{TYPE}-{YYYY}-" required />
                <x-form.input name="current_number" type="number" label="الرقم الحالي" :value="$item->current_number ?? 0" min="0" required />
                <x-form.input name="padding" type="number" label="عدد الخانات" :value="$item->padding ?? 6" min="1" max="12" required />
                <x-form.select name="reset_period" label="إعادة التصفير" required>
                    @foreach(['never' => 'بدون', 'yearly' => 'سنوي', 'monthly' => 'شهري'] as $value => $label)<option value="{{ $value }}" @selected(old('reset_period', $item->reset_period ?? 'yearly') === $value)>{{ $label }}</option>@endforeach
                </x-form.select>
            @else
                <x-form.input name="code" label="الكود" :value="$item->code" required />
                <x-form.input name="name" label="الاسم" :value="$item->name" required />
                @if($section === 'taxes')
                    <x-form.input name="rate" type="number" step="0.0001" label="النسبة %" :value="$item->rate" min="0" max="100" required />
                    <x-form.select name="tax_type" label="النوع" required>
                        @foreach(['sales','purchase','both','zero_rated','exempt'] as $type)<option value="{{ $type }}" @selected(old('tax_type', $item->tax_type) === $type)>{{ $type }}</option>@endforeach
                    </x-form.select>
                    <x-form.input name="effective_from" type="date" label="سارية من" :value="$item->effective_from?->format('Y-m-d')" />
                    <x-form.input name="effective_to" type="date" label="سارية إلى" :value="$item->effective_to?->format('Y-m-d')" />
                @elseif($section === 'units')
                    <x-form.input name="symbol" label="الرمز" :value="$item->symbol" required />
                    <x-form.select name="unit_type" label="نوع الوحدة" required>@foreach(['quantity','length','area','volume','weight','package'] as $type)<option value="{{ $type }}" @selected(old('unit_type', $item->unit_type) === $type)>{{ $type }}</option>@endforeach</x-form.select>
                    <x-form.input name="decimal_places" type="number" label="الخانات العشرية" :value="$item->decimal_places ?? 0" min="0" max="6" required />
                @elseif($section === 'payment-methods')
                    <x-form.select name="type" label="النوع" required>@foreach(['cash','card','bank_transfer','online','credit','other'] as $type)<option value="{{ $type }}" @selected(old('type', $item->type) === $type)>{{ $type }}</option>@endforeach</x-form.select>
                    <x-form.input name="sort_order" type="number" label="الترتيب" :value="$item->sort_order ?? 0" min="0" required />
                @else
                    <x-form.input name="sort_order" type="number" label="الترتيب" :value="$item->sort_order ?? 0" min="0" required />
                @endif
            @endif
        </div>
        @if($section === 'taxes')
            <label class="sw-check"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $item->is_default))> الضريبة الافتراضية</label>
            <label class="sw-check"><input type="checkbox" name="is_inclusive" value="1" @checked(old('is_inclusive', $item->is_inclusive))> السعر شامل الضريبة</label>
        @elseif($section === 'payment-methods')
            <label class="sw-check"><input type="checkbox" name="requires_reference" value="1" @checked(old('requires_reference', $item->requires_reference))> يتطلب رقمًا مرجعيًا</label>
            <label class="sw-check"><input type="checkbox" name="is_cash" value="1" @checked(old('is_cash', $item->is_cash))> نقدي</label>
        @elseif($section === 'fiscal-years')
            <label class="sw-check"><input type="checkbox" name="is_current" value="1" @checked(old('is_current', $item->is_current))> السنة الحالية</label>
        @endif
        @unless($section === 'fiscal-years')<label class="sw-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing ? $item->is_active : true))> نشط</label>@endunless
        <div class="sw-form-actions"><x-button type="submit">حفظ</x-button><a class="sw-button sw-button--outline" href="{{ route('reference.index', $section) }}">إلغاء</a></div>
    </form>
</x-card>
@endsection
