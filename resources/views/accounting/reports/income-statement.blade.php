@extends('layouts.app')

@section('title', 'قائمة الدخل')
@section('page-title', 'قائمة الدخل')

@section('content')
<div class="accounting-report-page">
    @include('accounting.reports._filters', ['allowComparative' => true])

    <section class="sw-card accounting-table-card">
        <div class="sw-card__header">
            <div>
                <h2>ملخص قائمة الدخل</h2>
                <p>الإيرادات والتكاليف والربحية خلال الفترة المحددة.</p>
            </div>
        </div>
        <div class="sw-table-wrap">
            <table class="sw-table accounting-summary-table">
                <tbody>
                    <tr><th>صافي الإيراد</th><td>{{ $revenue }}</td></tr>
                    <tr><th>تكلفة المبيعات</th><td>{{ $cost_of_sales }}</td></tr>
                    <tr><th>مجمل الربح</th><td>{{ $gross_profit }}</td></tr>
                    <tr><th>مصروفات التشغيل</th><td>{{ $operating_expenses }}</td></tr>
                    <tr><th>ربح التشغيل</th><td>{{ $operating_profit }}</td></tr>
                    <tr><th>صافي الربح</th><td>{{ $net_profit }}</td></tr>
                    <tr><th>هامش مجمل الربح</th><td>{{ $gross_margin ?? 'N/A' }}%</td></tr>
                    <tr><th>هامش صافي الربح</th><td>{{ $net_margin ?? 'N/A' }}%</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    @if($comparison)
        <section class="sw-card accounting-table-card">
            <div class="sw-card__header">
                <div>
                    <h2>المقارنة</h2>
                    <p>مقارنة القيم الحالية بالفترة المختارة.</p>
                </div>
            </div>
            <div class="sw-table-wrap">
                <table class="sw-table">
                    <thead>
                        <tr><th>البند</th><th>الحالي</th><th>المقارن</th><th>الفرق</th><th>النسبة</th></tr>
                    </thead>
                    <tbody>
                        @foreach($comparison as $name => $values)
                            <tr>
                                <td>{{ $name }}</td>
                                <td>{{ $values['current'] }}</td>
                                <td>{{ $values['previous'] }}</td>
                                <td>{{ $values['difference'] }}</td>
                                <td>{{ $values['percentage'] ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
