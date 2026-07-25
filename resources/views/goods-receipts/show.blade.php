@extends('layouts.app')
@section('title', $document->goods_receipt_number)
@section('page-title', 'استلام مشتريات')
@section('content')
<div class="sw-card">
    <div class="sw-detail-grid">
        <div><small>الرقم</small><strong>{{ $document->goods_receipt_number }}</strong></div>
        <div><small>المورد</small><strong>{{ $document->supplier->name }}</strong></div>
        <div><small>المخزن</small><strong>{{ $document->warehouse->name }}</strong></div>
        <div><small>الحالة</small><strong>{{ $document->status }}</strong></div>
    </div>
</div>
<div class="sw-card">
    <table class="sw-table">
        <thead><tr><th>المنتج</th><th>مستلم</th><th>مقبول</th><th>مرفوض</th><th>مجاني</th></tr></thead>
        <tbody>
        @foreach($document->items as $item)
            <tr>
                <td>{{ $item->product->name }}</td>
                <td>{{ $item->received_quantity }}</td>
                <td>{{ $item->accepted_quantity }}</td>
                <td>{{ $item->rejected_quantity }}</td>
                <td>{{ $item->free_quantity }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="sw-card">
    <h3>مرفقات الفحص</h3>
    @foreach($document->attachments as $attachment)
        <a class="sw-btn sw-btn-secondary" href="{{ route('attachments.download', $attachment) }}">{{ $attachment->original_name }}</a>
    @endforeach
    @can('inspect', $document)
        @if(!in_array($document->status, ['posted', 'cancelled'], true))
            <form method="POST" action="{{ route('goods-receipts.attachments.store', $document) }}" enctype="multipart/form-data">
                @csrf
                <select name="category" required>
                    <option value="goods_receipt_inspection">فحص</option>
                    <option value="goods_receipt_damage">تلف</option>
                    <option value="supplier_delivery_note">إذن تسليم المورد</option>
                </select>
                <input type="file" name="file" required>
                <button class="sw-btn" type="submit">رفع</button>
            </form>
        @endif
    @endcan
</div>
<div class="sw-page-actions">
    <form method="POST" action="{{ route('goods-receipts.receive', $document) }}">@csrf<button class="sw-btn">تأكيد الاستلام</button></form>
    <form method="POST" action="{{ route('goods-receipts.post', $document) }}">@csrf<button class="sw-btn">ترحيل للمخزون</button></form>
</div>
@endsection
