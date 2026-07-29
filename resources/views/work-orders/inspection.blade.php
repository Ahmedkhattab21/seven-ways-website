@extends('layouts.app')

@section('title', 'فحص الاستلام')
@section('breadcrumb', 'فحص الاستلام')
@section('page-title', 'فحص استلام السيارة')

@section('content')
@php
    $inspectionItem = $inspection->items->first();
    $vehicleReference = $inspection->workOrder->vehicle->plate_number ?: $inspection->workOrder->vehicle->vin;
@endphp

<div class="vehicle-inspection-page">
    <header class="vehicle-inspection-header">
        <div>
            <span class="vehicle-inspection-header__eyebrow">أمر العمل {{ $inspection->workOrder->work_order_number }}</span>
            <h1>فحص استلام السيارة</h1>
            <p>سجّل حالة السيارة عند الاستلام وارفع الصور الداعمة قبل إكمال الفحص.</p>
        </div>
        <div class="vehicle-inspection-header__meta">
            <x-status-badge :status="$inspection->status" />
            <strong>{{ $vehicleReference ?: 'سيارة بدون لوحة أو VIN' }}</strong>
        </div>
    </header>

    @can('update', $inspection)
        <form class="sw-card vehicle-inspection-form" method="POST" action="{{ route('vehicle-inspections.update', $inspection) }}">
            @csrf
            @method('PUT')

            <div class="vehicle-inspection-section-header">
                <div>
                    <h2>بيانات الفحص</h2>
                    <p>أدخل قراءة العداد ومستوى الوقود والملاحظات العامة.</p>
                </div>
            </div>

            <div class="vehicle-inspection-form__body">
                <div class="vehicle-inspection-main-grid">
                    <label>
                        <span>قراءة العداد</span>
                        <input type="number" name="odometer" value="{{ old('odometer', $inspection->odometer) }}" placeholder="مثال: 100000">
                    </label>
                    <label>
                        <span>مستوى الوقود %</span>
                        <input type="number" step="0.01" min="0" max="100" name="fuel_level" value="{{ old('fuel_level', $inspection->fuel_level) }}" placeholder="من 0 إلى 100">
                    </label>
                    <label class="vehicle-inspection-main-grid__notes">
                        <span>ملاحظات عامة</span>
                        <textarea name="general_notes" rows="4" placeholder="اكتب أي ملاحظات عامة على حالة السيارة">{{ old('general_notes', $inspection->general_notes) }}</textarea>
                    </label>
                </div>

                <fieldset class="vehicle-inspection-item">
                    <legend>عنصر الفحص</legend>
                    <div class="vehicle-inspection-item__grid">
                        <label>
                            <span>قسم الفحص</span>
                            <input name="items[0][section]" value="{{ old('items.0.section', $inspectionItem?->section ?? 'exterior') }}" required>
                        </label>
                        <label>
                            <span>كود العنصر</span>
                            <input name="items[0][item_code]" value="{{ old('items.0.item_code', $inspectionItem?->item_code ?? 'body') }}" required>
                        </label>
                        <label>
                            <span>اسم العنصر</span>
                            <input name="items[0][item_name]" value="{{ old('items.0.item_name', $inspectionItem?->item_name ?? 'هيكل السيارة') }}" required>
                        </label>
                        <label>
                            <span>الحالة</span>
                            <select name="items[0][condition]">
                                <option value="good" @selected(old('items.0.condition', $inspectionItem?->condition ?? 'good') === 'good')>جيد</option>
                                <option value="scratched" @selected(old('items.0.condition', $inspectionItem?->condition) === 'scratched')>خدوش</option>
                                <option value="damaged" @selected(old('items.0.condition', $inspectionItem?->condition) === 'damaged')>تلف</option>
                            </select>
                        </label>
                    </div>

                    <label class="vehicle-inspection-check">
                        <input type="checkbox" name="items[0][is_existing_damage]" value="1" @checked(old('items.0.is_existing_damage', $inspectionItem?->is_existing_damage))>
                        <span>هذا ضرر سابق موجود قبل الاستلام</span>
                    </label>
                </fieldset>

                <div class="vehicle-inspection-form__actions">
                    <button class="sw-button sw-button--primary">حفظ الفحص</button>
                </div>
            </div>
        </form>

        <section class="sw-card vehicle-inspection-photo-card">
            <div class="vehicle-inspection-section-header">
                <div>
                    <h2>رفع صورة للفحص</h2>
                    <p>الصور خاصة ولا يمكن الوصول إليها إلا للمستخدم المصرح له.</p>
                </div>
            </div>
            <form method="POST" enctype="multipart/form-data" action="{{ route('vehicle-inspections.photos.store', $inspection) }}">
                @csrf
                <label>
                    <span>ملف الصورة</span>
                    <input type="file" name="file" accept="image/*" required>
                </label>
                <label>
                    <span>تصنيف الصورة</span>
                    <select name="category">
                        <option value="inspection_overview">صورة عامة</option>
                        <option value="inspection_damage">ضرر</option>
                        <option value="inspection_odometer">العداد</option>
                        <option value="inspection_interior">الداخلية</option>
                    </select>
                </label>
                <button class="sw-button sw-button--secondary">رفع صورة خاصة</button>
            </form>
        </section>

        <form class="sw-card vehicle-inspection-complete" method="POST" action="{{ route('vehicle-inspections.complete', $inspection) }}">
            @csrf
            <div>
                <h2>إكمال الفحص</h2>
                <p>بعد الإكمال سيتم نقل أمر العمل للمرحلة التالية.</p>
            </div>
            <label>
                <span>اسم إقرار العميل</span>
                <input name="customer_name" placeholder="اسم العميل المقر بحالة السيارة">
            </label>
            <button class="sw-button sw-button--primary">إكمال الفحص</button>
        </form>
    @endcan

    <section class="sw-card vehicle-inspection-photos">
        <div class="vehicle-inspection-section-header">
            <div>
                <h2>صور الفحص</h2>
                <p>{{ $inspection->attachments->count() }} صورة مرفوعة</p>
            </div>
        </div>
        <div class="vehicle-inspection-photos__list">
            @forelse($inspection->attachments as $attachment)
                <a href="{{ route('attachments.download', $attachment) }}">
                    <span>{{ $attachment->original_name }}</span>
                    <small>{{ $attachment->category }}</small>
                </a>
            @empty
                <div class="vehicle-inspection-empty">
                    <strong>لا توجد صور مرفوعة.</strong>
                    <span>ارفع صور حالة السيارة لتوثيق الفحص.</span>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
