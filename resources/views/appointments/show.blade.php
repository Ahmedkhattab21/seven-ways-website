@extends('layouts.app')

@section('title', $appointment->appointment_number)

@section('content')
<div class="appointment-show-page">
    <div class="sw-page-header appointment-show-header">
        <div>
            <p class="appointment-show-header__eyebrow">تفاصيل الحجز</p>
            <h1>{{ $appointment->appointment_number }}</h1>
            <p class="appointment-show-header__meta">
                <span>{{ $appointment->customer->name }}</span>
                <span>{{ $appointment->branch->name }}</span>
                <x-status-badge :status="$appointment->status" />
            </p>
        </div>

        @can('update', $appointment)
            <a class="sw-button sw-button--outline" href="{{ route('appointments.edit', $appointment) }}">تعديل الحجز</a>
        @endcan
    </div>

    <x-card title="بيانات الحجز">
        <dl class="sw-details-grid appointment-details-grid">
            <div>
                <dt>بداية الموعد</dt>
                <dd>{{ $appointment->scheduled_start->format('Y-m-d H:i') }}</dd>
            </div>
            <div>
                <dt>نهاية الموعد</dt>
                <dd>{{ $appointment->scheduled_end->format('Y-m-d H:i') }}</dd>
            </div>
            <div>
                <dt>الفني المسؤول</dt>
                <dd>{{ $appointment->assignedEmployee?->name ?? 'غير مسند' }}</dd>
            </div>
            <div>
                <dt>السيارة</dt>
                <dd>{{ $appointment->vehicle->plate_number ?: ($appointment->vehicle->vin ?: 'غير محددة') }}</dd>
            </div>
        </dl>
    </x-card>

    <x-card title="الخدمات" subtitle="الخدمات المخطط تنفيذها خلال الموعد.">
        <div class="sw-table-wrap">
            <table class="sw-table">
                <thead>
                    <tr>
                        <th>الخدمة</th>
                        <th>الكمية</th>
                        <th>المدة</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($appointment->services as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ $item->estimated_duration_minutes }} دقيقة</td>
                            <td><x-status-badge :status="$item->status" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">لا توجد خدمات مرتبطة بهذا الحجز.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="الإجراءات" subtitle="الإجراءات المتاحة حسب حالة الحجز وصلاحياتك.">
        <div class="appointment-actions">
            @can('confirm', $appointment)
                <form method="POST" action="{{ route('appointments.confirm', $appointment) }}" class="appointment-action-panel">
                    @csrf
                    <div>
                        <h3>تأكيد الحجز</h3>
                        <p>تأكيد الموعد قبل وصول العميل.</p>
                    </div>
                    <div class="sw-form-actions">
                        <x-button type="submit">تأكيد الحجز</x-button>
                    </div>
                </form>
            @endcan

            @if($activeWorkOrder)
                <div class="appointment-action-panel">
                    <div>
                        <h3>أمر العمل جاهز</h3>
                        <p>أمر العمل رقم {{ $activeWorkOrder->work_order_number }} مرتبط بهذا الحجز.</p>
                    </div>
                    <div class="sw-form-actions">
                        <a class="sw-button sw-button--primary" href="{{ route('work-orders.show', $activeWorkOrder) }}">فتح أمر العمل</a>
                    </div>
                </div>
            @elseif($appointment->status === 'checked_in')
                @can('checkIn', $appointment)
                    <form method="POST" action="{{ route('appointments.check-in', $appointment) }}" class="appointment-action-panel sw-form">
                        @csrf
                        <div>
                            <h3>استكمال إنشاء أمر العمل</h3>
                            <p>تم تسجيل وصول العميل، لكن لم يكتمل إنشاء أمر العمل. يمكنك استكمال العملية بعد معالجة سبب التعطل.</p>
                        </div>
                        <div class="sw-form-actions">
                            <x-button type="submit" :disabled="! $defaultWorkOrderWarehouse">استكمال إنشاء أمر العمل</x-button>
                        </div>
                        @unless($defaultWorkOrderWarehouse)
                            <x-alert type="warning">
                                لا يوجد مستودع افتراضي صالح لصرف خامات أوامر العمل في هذا الفرع.
                                @if(auth()->user()->hasRole('system_admin') || auth()->user()->hasPermission('branch_settings.manage'))
                                    <a href="{{ route('branch-settings.edit') }}">تحديد مستودع أوامر العمل</a>
                                @endif
                            </x-alert>
                        @endunless
                    </form>
                @endcan
            @elseif(in_array($appointment->status, ['pending', 'confirmed'], true))
                @can('checkIn', $appointment)
                <form method="POST" action="{{ route('appointments.check-in', $appointment) }}" class="appointment-action-panel sw-form">
                    @csrf
                    <div>
                        <h3>تسجيل وصول العميل وبدء أمر العمل</h3>
                        <p>سيتم إنشاء أمر العمل تلقائيًا باستخدام مستودع الفرع الافتراضي.</p>
                    </div>
                    <div class="sw-form-grid">
                        <x-form.input name="arrival_notes" label="ملاحظات الوصول" :value="old('arrival_notes')" />
                        <x-form.input name="odometer_snapshot" type="number" label="قراءة العداد" :value="old('odometer_snapshot')" min="0" />
                    </div>
                    <div class="sw-form-actions">
                        @if($defaultWorkOrderWarehouse)
                            <x-button type="submit">تسجيل وصول العميل وبدء أمر العمل</x-button>
                        @else
                            <x-button type="submit" disabled>تسجيل وصول العميل وبدء أمر العمل</x-button>
                        @endif
                    </div>
                    @unless($defaultWorkOrderWarehouse)
                        <x-alert type="warning">
                            لا يوجد مستودع افتراضي صالح لصرف خامات أوامر العمل في هذا الفرع.
                            @if(auth()->user()->hasRole('system_admin') || auth()->user()->hasPermission('branch_settings.manage'))
                                <a href="{{ route('branch-settings.edit') }}">تحديد مستودع أوامر العمل</a>
                            @endif
                        </x-alert>
                    @endunless
                </form>
                @endcan
            @endif

            @can('cancel', $appointment)
                <form method="POST" action="{{ route('appointments.cancel', $appointment) }}" class="appointment-action-panel appointment-action-panel--danger sw-form">
                    @csrf
                    <div>
                        <h3>إلغاء الحجز</h3>
                        <p>أدخل سبب الإلغاء وحدد قرار العربون التشغيلي.</p>
                    </div>
                    <div class="sw-form-grid">
                        <x-form.input name="reason" label="سبب الإلغاء" :value="old('reason')" required />
                        <x-form.select name="deposit_decision" label="قرار العربون">
                            <option value="pending_decision" @selected(old('deposit_decision') === 'pending_decision')>القرار لاحقًا</option>
                            <option value="refunded" @selected(old('deposit_decision') === 'refunded')>مردود تشغيليًا</option>
                            <option value="forfeited" @selected(old('deposit_decision') === 'forfeited')>مصادر</option>
                        </x-form.select>
                    </div>
                    <div class="sw-form-actions">
                        <x-button type="submit" variant="danger">إلغاء الحجز</x-button>
                    </div>
                </form>
            @endcan
        </div>
    </x-card>

    @if(auth()->user()->hasPermission('appointment_deposits.view'))
        <x-card title="العربون التشغيلي" subtitle="سجل تشغيلي فقط ولا ينشئ صندوقًا أو قيدًا محاسبيًا أو استردادًا ماليًا.">
            <div class="appointment-deposits">
                @forelse($appointment->deposits as $deposit)
                    <div class="appointment-deposit-item">
                        <strong>{{ $deposit->receipt_number }}</strong>
                        <span>{{ $deposit->amount }}</span>
                        <x-status-badge :status="$deposit->status" />
                    </div>
                @empty
                    <p class="appointment-empty-state">لا توجد عربونات مسجلة لهذا الحجز.</p>
                @endforelse
            </div>

            @if(auth()->user()->hasPermission('appointment_deposits.record'))
                <form method="POST" action="{{ route('appointments.deposits.store', $appointment) }}" class="appointment-deposit-form sw-form">
                    @csrf
                    <div class="sw-form-grid">
                        <x-form.input name="amount" type="number" label="قيمة العربون" :value="old('amount')" step="0.0001" min="0.0001" required />
                        <x-form.select name="payment_method_id" label="طريقة الدفع" required>
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>{{ $method->name }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.input name="reference_number" label="المرجع" :value="old('reference_number')" />
                        <x-form.input name="received_at" type="datetime-local" label="تاريخ الاستلام" :value="old('received_at', now()->format('Y-m-d\TH:i'))" required />
                    </div>
                    <div class="sw-form-actions">
                        <x-button type="submit">تسجيل العربون</x-button>
                    </div>
                </form>
            @endif
        </x-card>
    @endif
</div>
@endsection
