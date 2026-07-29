@extends('layouts.app')

@section('title', 'أمر عمل جديد')
@section('breadcrumb', 'أمر عمل جديد')
@section('page-title', 'إنشاء أمر عمل')

@section('content')
<div class="work-order-create-page">
    <header class="work-order-create-header">
        <div>
            <span class="work-order-create-header__eyebrow">التشغيل اليومي</span>
            <h1>إنشاء أمر عمل</h1>
            <p>حدّد مصدر الأمر وبيانات العميل، ثم أضف الخدمة المطلوبة.</p>
        </div>

        <a class="sw-button sw-button--outline" href="{{ route('work-orders.index') }}">العودة لأوامر العمل</a>
    </header>

    <form class="work-order-create-form" method="POST" action="{{ route('work-orders.store') }}">
        @csrf

        <section class="sw-card">
            <div class="work-order-create-section__header">
                <div>
                    <h2>بيانات أمر العمل</h2>
                    <p>اختر المصدر والفرع والمخزن، ثم اربط العميل والسيارة.</p>
                </div>
            </div>

            <div class="work-order-create-grid">
                <label class="sw-field">
                    <span class="sw-field__label">المصدر <span class="sw-field__required">*</span></span>
                    <select class="sw-input" name="source" required>
                        <option value="appointment" @selected(old('source', 'appointment') === 'appointment')>موعد تم تسجيل وصوله</option>
                        <option value="direct" @selected(old('source') === 'direct')>دخول مباشر</option>
                    </select>
                </label>

                <label class="sw-field">
                    <span class="sw-field__label">الموعد</span>
                    <select class="sw-input" name="appointment_id">
                        <option value="">بدون موعد</option>
                        @foreach($appointments as $appointment)
                            <option value="{{ $appointment->id }}" @selected((string) old('appointment_id') === (string) $appointment->id)>
                                {{ $appointment->appointment_number }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="sw-field">
                    <span class="sw-field__label">الفرع</span>
                    <select class="sw-input" name="branch_id">
                        <option value="">اختر الفرع</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="sw-field">
                    <span class="sw-field__label">المخزن <span class="sw-field__required">*</span></span>
                    <select class="sw-input" name="warehouse_id" required>
                        <option value="">اختر المخزن</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id)>
                                {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="sw-field">
                    <span class="sw-field__label">العميل</span>
                    <select class="sw-input" name="customer_id">
                        <option value="">اختر العميل</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>
                                {{ $customer->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="sw-field">
                    <span class="sw-field__label">السيارة</span>
                    <select class="sw-input" name="vehicle_id">
                        <option value="">اختر السيارة</option>
                        @foreach($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id') === (string) $vehicle->id)>
                                {{ $vehicle->plate_number ?: $vehicle->vin }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        <section class="sw-card">
            <div class="work-order-create-section__header">
                <div>
                    <h2>خدمة الدخول المباشر</h2>
                    <p>أدخل تفاصيل الخدمة والمدة والسعر المتوقعين.</p>
                </div>
            </div>

            <div class="work-order-create-service-grid">
                <label class="sw-field work-order-create-service-grid__service">
                    <span class="sw-field__label">الخدمة</span>
                    <select class="sw-input" name="services[0][service_id]">
                        @foreach($services as $service)
                            <option value="{{ $service->id }}" @selected((string) old('services.0.service_id') === (string) $service->id)>
                                {{ $service->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="sw-field work-order-create-service-grid__description">
                    <span class="sw-field__label">وصف الخدمة</span>
                    <input class="sw-input" name="services[0][description]" value="{{ old('services.0.description') }}" placeholder="اكتب وصفًا مختصرًا للخدمة">
                </label>

                <label class="sw-field">
                    <span class="sw-field__label">الكمية</span>
                    <input class="sw-input" type="number" min="0.000001" step="0.000001" name="services[0][quantity]" value="{{ old('services.0.quantity', 1) }}">
                </label>

                <label class="sw-field">
                    <span class="sw-field__label">المدة بالدقائق</span>
                    <input class="sw-input" type="number" min="0" name="services[0][planned_duration_minutes]" value="{{ old('services.0.planned_duration_minutes') }}" placeholder="مثال: 60">
                </label>

                <label class="sw-field">
                    <span class="sw-field__label">السعر المتوقع</span>
                    <input class="sw-input" type="number" min="0" step="0.0001" name="services[0][unit_price_snapshot]" value="{{ old('services.0.unit_price_snapshot') }}" placeholder="0.00">
                </label>
            </div>
        </section>

        <div class="work-order-create-actions">
            <button class="sw-button sw-button--primary" type="submit">إنشاء أمر العمل</button>
            <a class="sw-button sw-button--outline" href="{{ route('work-orders.index') }}">إلغاء</a>
        </div>
    </form>
</div>
@endsection
