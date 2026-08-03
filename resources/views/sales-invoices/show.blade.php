@extends('layouts.app')
@section('title', $invoice->invoice_number)
@section('page-title', $invoice->invoice_number)
@section('content')
@php
    $statusLabels = [
        'draft' => 'مسودة',
        'pending_approval' => 'بانتظار الاعتماد',
        'approved' => 'معتمدة',
        'issued' => 'صادرة',
        'partially_paid' => 'مدفوعة جزئيًا',
        'paid' => 'مدفوعة',
        'overdue' => 'متأخرة',
        'cancelled' => 'ملغاة',
        'void' => 'مفرغة',
    ];
    $actionLabels = [
        'submit' => 'إرسال للاعتماد',
        'approve' => 'اعتماد الفاتورة',
        'issue' => 'إصدار الفاتورة',
    ];
@endphp
<section class="sw-card">
    <header class="sw-card__header">
        <div>
            <h2 class="sw-card__title">{{ $invoice->invoice_number }}</h2>
            <p class="sw-card__subtitle">{{ $invoice->customer_name_snapshot }} — {{ $invoice->branch->name }} — {{ $statusLabels[$invoice->status] ?? $invoice->status }}</p>
        </div>
        <div class="sw-form-actions">
            <a class="sw-button sw-button--outline" href="{{ route('sales-invoices.print', $invoice) }}">طباعة</a>
            @if(auth()->user()->hasPermission('sales_invoices.share'))
                <form method="POST" action="{{ route('sales-invoices.share', $invoice) }}">@csrf<button class="sw-button sw-button--primary">إرسال الفاتورة عبر واتساب</button></form>
            @endif
        </div>
    </header>
    <div class="sw-card__body">
        <p>الإجمالي: {{ $invoice->total }} | المدفوع: {{ $invoice->paid_amount }} | المتبقي: {{ $invoice->balance_due }}</p>
        @if(in_array($invoice->status, ['issued', 'partially_paid', 'paid', 'overdue', 'credited'], true) && $invoice->items->where('item_type', 'product')->whereNull('issued_movement_id')->isNotEmpty())
            <div class="sw-alert sw-alert--warning">تحذير إداري: توجد منتجات صادرة في هذه الفاتورة بدون حركة صرف مخزون. راجع تدقيق المخزون قبل إجراء أي تصحيح.</div>
        @endif
        <table class="sw-table"><thead><tr><th>المنتج</th><th>الكمية</th><th>المخزن</th><th>حركة الصرف</th><th>الكمية المصروفة</th><th>السعر</th><th>الخصم</th><th>الضريبة</th><th>الإجمالي</th></tr></thead><tbody>
        @foreach($invoice->items as $item)
            @php($warranty = $item->warranty_snapshot ?? [])
            <tr><td><strong>{{ $warranty['product_name'] ?? $item->product?->name ?? $item->description }}</strong><br><small>SKU: {{ $warranty['product_sku'] ?? $item->product?->sku ?? '—' }} | الشركة: {{ $warranty['manufacturer'] ?? $item->product?->brand?->name ?? '—' }}</small>@if($item->warranty_applies)<br><small>يشمل ضمانًا</small>@endif</td><td>{{ $item->quantity }}</td><td>{{ $item->item_type === 'product' ? ($item->warehouse?->name ?? '—') : '—' }}</td><td>{{ $item->issuedMovement?->movement_number ?? '—' }}</td><td>{{ $item->issuedMovement?->stock_quantity ?? '—' }}</td><td>{{ $item->unit_price }}</td><td>{{ $item->discount_amount }}</td><td>{{ $item->tax_amount }}</td><td>{{ $item->total }}</td></tr>
        @endforeach
        </tbody></table>
    </div>
</section>

@if($invoice->items->where('item_type', 'product')->isNotEmpty())
<section class="sw-card">
    <header class="sw-card__header"><h2 class="sw-card__title">بيانات المنتجات والضمان</h2></header>
    <div class="sw-card__body">
        @foreach($invoice->items->where('item_type', 'product') as $item)
            @php($warranty = $item->warranty_snapshot ?? [])
            <article class="sw-card">
                <strong>{{ $warranty['product_name'] ?? $item->product?->name ?? $item->description }}</strong>
                <p>SKU: {{ $warranty['product_sku'] ?? $item->product?->sku ?? '—' }} | حالة الضمان: {{ $item->warranty_applies ? 'مسجل' : 'غير مسجل لهذا المنتج' }}</p>
                <p>الشركة: {{ $warranty['manufacturer'] ?? $item->product?->brand?->name ?? '—' }} | اسم الرول: {{ $warranty['roll_name'] ?? '—' }} | كود/رقم الرول: {{ $warranty['film_code'] ?? '—' }}</p>
                <p>نوع الفيلم: {{ $warranty['film_type'] ?? '—' }} | منطقة التطبيق: {{ $warranty['application_area'] ?? '—' }}</p>
                @if($item->warranty_applies)
                    <p>من {{ $warranty['start_date'] ?? '—' }} إلى {{ ($warranty['duration_unit'] ?? null) === 'lifetime' ? 'مدى الحياة' : ($warranty['end_date'] ?? '—') }}</p>
                    @if(!empty($warranty['terms']))<p>{{ $warranty['terms'] }}</p>@endif
                @endif
            </article>
        @endforeach
    </div>
</section>
@if($invoice->items->where('warranty_applies', true)->isNotEmpty())
    <section class="sw-card"><div class="sw-card__body"><strong>بطاقة الضمان الإلكترونية:</strong> هذه الفاتورة رقم {{ $invoice->invoice_number }} هي مستند الضمان، ويمكن للعميل عرضها من رابط المشاركة الآمن المرسل إليه.</div></section>
@endif
@endif

@foreach(['draft'=>'submit','pending_approval'=>'approve','approved'=>'issue'] as $status=>$action)
    @if($invoice->status === $status)
        @can($action, $invoice)
            <form class="sw-card" method="POST" action="{{ route('sales-invoices.action', [$invoice, $action]) }}">@csrf<button class="sw-button sw-button--primary">{{ $actionLabels[$action] }}</button></form>
        @endcan
    @endif
@endforeach
@if(in_array($invoice->status, ['issued','partially_paid','paid','overdue']))
    <a class="sw-button sw-button--outline" href="{{ route('sales-credit-notes.create', $invoice) }}">إشعار دائن</a>
@endif

@if(auth()->user()->hasPermission('sales_invoices.view_shares') && $invoice->shares->isNotEmpty())
<section class="sw-card">
    <header class="sw-card__header"><h2 class="sw-card__title">سجل المشاركة</h2></header>
    <table class="sw-table"><thead><tr><th>القناة</th><th>الحالة</th><th>أُنشئ بواسطة</th><th>الإنشاء</th><th>الفتح</th><th>الانتهاء</th></tr></thead><tbody>
    @foreach($invoice->shares as $share)<tr><td>{{ $share->channel }}</td><td>{{ $share->status }}</td><td>{{ $share->generatedBy?->name }}</td><td>{{ $share->generated_at }}</td><td>{{ $share->opened_at ?: '—' }}</td><td>{{ $share->expires_at }}</td></tr>@endforeach
    </tbody></table>
</section>
@endif
@endsection
