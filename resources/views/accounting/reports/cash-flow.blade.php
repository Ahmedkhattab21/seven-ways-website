@extends('layouts.app')

@section('title', 'التدفقات النقدية')
@section('page-title', 'التدفقات النقدية')

@section('content')
<div class="accounting-report-page">
    @include('accounting.reports._filters')

    @if($warning)
        <div class="sw-alert accounting-report-status">{{ $warning }}</div>
    @endif

    <section class="sw-card accounting-table-card">
        <div class="sw-card__header">
            <div>
                <h2>ملخص التدفقات النقدية</h2>
                <p>حركة النقدية موزعة حسب الأنشطة خلال الفترة المحددة.</p>
            </div>
        </div>
        <div class="sw-table-wrap">
            <table class="sw-table accounting-summary-table">
                <tbody>
                    <tr><th>النقدية الافتتاحية</th><td>{{ $opening_cash }}</td></tr>
                    <tr><th>الأنشطة التشغيلية</th><td>{{ $operating }}</td></tr>
                    <tr><th>الأنشطة الاستثمارية</th><td>{{ $investing }}</td></tr>
                    <tr><th>الأنشطة التمويلية</th><td>{{ $financing }}</td></tr>
                    <tr><th>غير مصنف</th><td>{{ $unclassified }}</td></tr>
                    <tr><th>صافي التغير</th><td>{{ $net_change }}</td></tr>
                    <tr><th>النقدية الختامية</th><td>{{ $closing_cash }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
