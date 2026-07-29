@extends('layouts.app')
@section('title', $quotation->quotation_number)
@section('content')
@php
    $statusLabels = [
        'draft' => 'مسودة',
        'pending_approval' => 'في انتظار الاعتماد',
        'approved' => 'معتمد',
        'sent' => 'مُرسل للعميل',
        'accepted' => 'مقبول',
        'rejected' => 'مرفوض',
        'expired' => 'منتهي',
        'converted' => 'تم تحويله',
        'cancelled' => 'ملغي',
        'superseded' => 'مستبدل بإصدار أحدث',
    ];
    $currencyCode = $quotation->currency?->code ?? '';
    $money = fn ($value) => number_format((float) $value, 2).' '.$currencyCode;
@endphp

<div class="configuration-page quotation-show-layout">
    <div class="sw-page-header quotation-show-header">
        <div>
            <span class="quotation-show-eyebrow">عرض سعر</span>
            <h1>{{ $quotation->quotation_number }} <small>V{{ $quotation->version_number }}</small></h1>
            <div class="quotation-show-meta">
                <span>{{ $quotation->customer->name }}</span>
                <span>{{ $quotation->branch->name }}</span>
                <span class="quotation-status quotation-status--{{ $quotation->status }}">
                    {{ $statusLabels[$quotation->status] ?? $quotation->status }}
                </span>
            </div>
        </div>
        <div class="sw-actions quotation-show-header__actions">
            <a class="sw-btn" href="{{ route('quotations.print', $quotation) }}">طباعة</a>
            @can('update', $quotation)
                <a class="sw-btn sw-btn--primary" href="{{ route('quotations.edit', $quotation) }}">تعديل العرض</a>
            @endcan
        </div>
    </div>

    <section class="sw-card quotation-items-card">
        <div class="sw-card__header">
            <div>
                <h2 class="sw-card__title">عناصر عرض السعر</h2>
                <p class="sw-card__subtitle">{{ $quotation->items->count() }} عنصر داخل العرض</p>
            </div>
        </div>
        <div class="sw-table-scroll">
            <table class="sw-table">
                <thead>
                    <tr>
                        <th>الوصف</th>
                        <th>الكمية</th>
                        <th>سعر الوحدة</th>
                        <th>الخصم</th>
                        <th>الضريبة</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quotation->items as $item)
                        <tr>
                            <td class="quotation-item-description">{{ $item->description }}</td>
                            <td>{{ number_format((float) $item->quantity, 2) }}</td>
                            <td>{{ $money($item->unit_price) }}</td>
                            <td>{{ $money($item->discount_amount) }}</td>
                            <td>{{ $money($item->tax_amount) }}</td>
                            <td><strong>{{ $money($item->total) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="sw-card quotation-summary-card">
        <div class="quotation-summary-grid">
            <div>
                <span>قبل الخصم</span>
                <strong>{{ $money($quotation->subtotal) }}</strong>
            </div>
            <div>
                <span>الخصم العام</span>
                <strong>{{ $money($quotation->discount_amount) }}</strong>
            </div>
            <div>
                <span>الضريبة</span>
                <strong>{{ $money($quotation->tax_amount) }}</strong>
            </div>
            <div class="quotation-summary-total">
                <span>الإجمالي النهائي</span>
                <strong>{{ $money($quotation->total) }}</strong>
            </div>
        </div>

        @can('viewCost', $quotation)
            <div class="quotation-cost-summary">
                <span>التكلفة التقديرية: <strong>{{ $quotation->estimated_total_cost !== null ? $money($quotation->estimated_total_cost) : 'غير متاحة' }}</strong></span>
                <span>الهامش: <strong>{{ $quotation->estimated_margin !== null ? $money($quotation->estimated_margin) : 'غير متاح' }}</strong></span>
            </div>
        @endcan
    </section>

    <section class="sw-card quotation-actions-card">
        <div class="sw-card__header">
            <div>
                <h2 class="sw-card__title">الإجراءات</h2>
                <p class="sw-card__subtitle">الإجراءات المتاحة حسب حالة العرض وصلاحياتك.</p>
            </div>
        </div>
        <div class="sw-card__body quotation-action-list">
            @can('submit', $quotation)
                <form method="POST" action="{{ route('quotations.submit', $quotation) }}">
                    @csrf
                    <div>
                        <strong>إرسال للمراجعة</strong>
                        <small>إرسال المسودة إلى دورة الاعتماد.</small>
                    </div>
                    <button class="sw-btn sw-btn--primary">إرسال للاعتماد</button>
                </form>
            @endcan

            @can('approve', $quotation)
                <form method="POST" action="{{ route('quotations.approve', $quotation) }}">
                    @csrf
                    <label>
                        ملاحظات الاعتماد
                        <input name="approval_notes" placeholder="اكتب ملاحظات الاعتماد إن وجدت">
                    </label>
                    <button class="sw-btn sw-btn--primary">اعتماد العرض</button>
                </form>
            @endcan

            @can('send', $quotation)
                <form method="POST" action="{{ route('quotations.send', $quotation) }}">
                    @csrf
                    <div>
                        <strong>إرسال العرض للعميل</strong>
                        <small>تسجيل أن العرض تم إرساله للعميل.</small>
                    </div>
                    <button class="sw-btn">تسجيل الإرسال</button>
                </form>
            @endcan

            @can('accept', $quotation)
                <form method="POST" action="{{ route('quotations.accept', $quotation) }}">
                    @csrf
                    <label>
                        طريقة الموافقة
                        <select name="acceptance_method">
                            <option value="in_person">حضوري</option>
                            <option value="phone">هاتف</option>
                            <option value="whatsapp">واتساب</option>
                            <option value="email">بريد إلكتروني</option>
                        </select>
                    </label>
                    <label>
                        اسم الموافق
                        <input name="accepted_by_name" placeholder="اسم الشخص الذي وافق">
                    </label>
                    <button class="sw-btn sw-btn--primary">قبول العرض</button>
                </form>
            @endcan

            @can('createVersion', $quotation)
                <form method="POST" action="{{ route('quotations.version', $quotation) }}">
                    @csrf
                    <label>
                        سبب الإصدار الجديد
                        <input name="reason" required placeholder="اكتب سبب إنشاء إصدار جديد">
                    </label>
                    <button class="sw-btn">إنشاء إصدار</button>
                </form>
            @endcan

            @if($quotation->status === 'accepted' && $quotation->appointments->isEmpty())
                <form method="POST" action="{{ route('quotations.appointment', $quotation) }}">
                    @csrf
                    <label>
                        بداية الموعد
                        <input type="datetime-local" name="scheduled_start" required>
                    </label>
                    <label>
                        نهاية الموعد
                        <input type="datetime-local" name="scheduled_end" required>
                    </label>
                    <input type="hidden" name="priority" value="normal">
                    <button class="sw-btn sw-btn--primary">تحويل إلى حجز</button>
                </form>
            @endif
        </div>
    </section>
</div>
@endsection
