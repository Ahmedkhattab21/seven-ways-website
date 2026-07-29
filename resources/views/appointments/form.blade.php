@extends('layouts.app')

@section('title', $appointment->exists ? 'تعديل حجز' : 'إضافة حجز')

@section('content')
@php
    $rows = old(
        'services',
        $appointment->exists
            ? $appointment->services->toArray()
            : [['quantity' => 1, 'estimated_duration_minutes' => 60, 'unit_price_snapshot' => 0, 'total_snapshot' => 0]]
    );
@endphp

<div class="appointment-form-page">
    <div class="sw-page-header appointment-form-header">
        <div>
            <p class="appointment-form-header__eyebrow">التشغيل اليومي</p>
            <h1>{{ $appointment->exists ? 'تعديل الحجز' : 'حجز جديد' }}</h1>
            <p>الحجز لا ينشئ أمر عمل أو حركة مخزون قبل بدء التنفيذ.</p>
        </div>
        <a class="sw-button sw-button--outline" href="{{ route('appointments.index') }}">العودة للحجوزات</a>
    </div>

    <form
        class="appointment-form sw-form"
        method="POST"
        action="{{ $appointment->exists ? route('appointments.update', $appointment) : route('appointments.store') }}"
    >
        @csrf
        @if($appointment->exists)
            @method('PUT')
        @endif

        <x-card title="بيانات الحجز" subtitle="حدد العميل والسيارة والموعد والفرع المسؤول.">
            <div class="sw-form-grid appointment-form-grid">
                <x-form.select name="branch_id" label="الفرع" required>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id', $appointment->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </x-form.select>

                <x-form.select name="customer_id" label="العميل" required>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) old('customer_id', $appointment->customer_id) === (string) $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </x-form.select>

                <x-form.select name="vehicle_id" label="السيارة" required>
                    @foreach($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected((string) old('vehicle_id', $appointment->vehicle_id) === (string) $vehicle->id)>{{ $vehicle->plate_number ?: $vehicle->vin }}</option>
                    @endforeach
                </x-form.select>

                <x-form.select name="assigned_employee_id" label="الفني">
                    <option value="">إسناد لاحق</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((string) old('assigned_employee_id', $appointment->assigned_employee_id) === (string) $employee->id)>{{ $employee->name }}</option>
                    @endforeach
                </x-form.select>

                <x-form.input
                    name="scheduled_start"
                    type="datetime-local"
                    label="بداية الموعد"
                    :value="old('scheduled_start', $appointment->scheduled_start?->format('Y-m-d\TH:i'))"
                    required
                />
                <x-form.input
                    name="scheduled_end"
                    type="datetime-local"
                    label="نهاية الموعد"
                    :value="old('scheduled_end', $appointment->scheduled_end?->format('Y-m-d\TH:i'))"
                    required
                />

                <x-form.select name="booking_source" label="مصدر الحجز" required>
                    @foreach(['walk_in' => 'حضوري', 'phone' => 'هاتف', 'whatsapp' => 'واتساب', 'website' => 'الموقع', 'other' => 'أخرى'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('booking_source', $appointment->booking_source ?? 'walk_in') === $value)>{{ $label }}</option>
                    @endforeach
                </x-form.select>

                <x-form.select name="priority" label="الأولوية" required>
                    @foreach(['low' => 'منخفضة', 'normal' => 'عادية', 'high' => 'مرتفعة', 'urgent' => 'عاجلة'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('priority', $appointment->priority ?? 'normal') === $value)>{{ $label }}</option>
                    @endforeach
                </x-form.select>
            </div>
        </x-card>

        <x-card title="العربون" subtitle="حدد ما إذا كان الحجز يتطلب عربونًا تشغيليًا.">
            <div class="appointment-deposit-settings">
                <div>
                    <input type="hidden" name="deposit_required" value="0">
                    <x-form.checkbox
                        name="deposit_required"
                        label="عربون مطلوب"
                        :checked="(bool) old('deposit_required', $appointment->deposit_required)"
                    />
                </div>
                <x-form.input
                    name="deposit_amount"
                    type="number"
                    label="قيمة العربون"
                    :value="old('deposit_amount', $appointment->deposit_amount ?? 0)"
                    step="0.0001"
                    min="0"
                />
            </div>
        </x-card>

        <x-card title="الخدمات" subtitle="الخدمات المخطط تنفيذها خلال هذا الحجز.">
            <div class="appointment-service-list">
                @foreach($rows as $i => $row)
                    <fieldset class="appointment-service-item">
                        <legend>الخدمة رقم {{ $i + 1 }}</legend>
                        <div class="sw-form-grid appointment-form-grid">
                            <x-form.select
                                :name="'services['.$i.'][service_id]'"
                                label="الخدمة"
                                class="appointment-service-select"
                                required
                            >
                                <option value="">اختر الخدمة</option>
                                @foreach($services as $service)
                                    <option
                                        value="{{ $service->id }}"
                                        data-branch-ids="{{ $service->branchServices->pluck('branch_id')->implode(',') }}"
                                        @selected((string) ($row['service_id'] ?? null) === (string) $service->id)
                                    >{{ $service->name }}</option>
                                @endforeach
                            </x-form.select>

                            <x-form.input
                                :name="'services['.$i.'][description]'"
                                label="الوصف"
                                :value="$row['description'] ?? ''"
                                required
                            />
                            <x-form.input
                                :name="'services['.$i.'][quantity]'"
                                type="number"
                                label="الكمية"
                                :value="$row['quantity'] ?? 1"
                                step="0.000001"
                                min="0.000001"
                                required
                            />
                            <x-form.input
                                :name="'services['.$i.'][estimated_duration_minutes]'"
                                type="number"
                                label="المدة بالدقائق"
                                :value="$row['estimated_duration_minutes'] ?? 60"
                                min="1"
                                required
                            />
                            <x-form.input
                                :name="'services['.$i.'][unit_price_snapshot]'"
                                type="number"
                                label="سعر Snapshot"
                                :value="$row['unit_price_snapshot'] ?? 0"
                                step="0.0001"
                                min="0"
                            />
                            <x-form.input
                                :name="'services['.$i.'][total_snapshot]'"
                                type="number"
                                label="إجمالي Snapshot"
                                :value="$row['total_snapshot'] ?? 0"
                                step="0.0001"
                                min="0"
                            />
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </x-card>

        <x-card title="الملاحظات" subtitle="أضف الملاحظات التي يحتاجها فريق التشغيل.">
            <div class="sw-form-grid appointment-form-grid">
                <x-form.textarea name="customer_notes" label="ملاحظات العميل">{{ old('customer_notes', $appointment->customer_notes) }}</x-form.textarea>
                <x-form.textarea name="internal_notes" label="ملاحظات داخلية">{{ old('internal_notes', $appointment->internal_notes) }}</x-form.textarea>
            </div>
        </x-card>

        <div class="appointment-form-actions">
            <a class="sw-button sw-button--outline" href="{{ route('appointments.index') }}">إلغاء</a>
            <x-button type="submit">{{ $appointment->exists ? 'حفظ التعديلات' : 'حفظ الحجز' }}</x-button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const branchSelect = document.getElementById('branch_id');
        const serviceSelects = document.querySelectorAll('.appointment-service-select');

        const filterServices = () => {
            const branchId = branchSelect?.value ?? '';

            serviceSelects.forEach((select) => {
                let availableCount = 0;

                Array.from(select.options).forEach((option) => {
                    if (! option.value) {
                        return;
                    }

                    const branchIds = (option.dataset.branchIds ?? '').split(',').filter(Boolean);
                    const isAvailable = branchIds.includes(branchId);
                    option.hidden = ! isAvailable;
                    option.disabled = ! isAvailable;
                    availableCount += isAvailable ? 1 : 0;
                });

                if (select.selectedOptions[0]?.disabled) {
                    select.value = '';
                }

                let message = select.closest('.sw-field')?.querySelector('.appointment-service-empty');
                if (! message) {
                    message = document.createElement('p');
                    message.className = 'sw-field__help appointment-service-empty';
                    select.closest('.sw-field')?.appendChild(message);
                }
                message.textContent = availableCount === 0 ? 'لا توجد خدمات متاحة في الفرع المحدد.' : '';
            });
        };

        branchSelect?.addEventListener('change', filterServices);
        filterServices();
    });
</script>
@endsection
