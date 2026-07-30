@extends('layouts.app')

@section('title', 'الميزانية العمومية')
@section('page-title', 'الميزانية العمومية')

@section('content')
<div class="accounting-report-page">
    @include('accounting.reports._filters', ['allowComparative' => true])

    <div class="sw-alert accounting-report-status">
        {{ $balanced ? 'المعادلة المحاسبية متوازنة.' : 'فرق المعادلة المحاسبية: '.$difference }}
    </div>

    <section class="sw-card accounting-table-card">
        <div class="sw-card__header">
            <div>
                <h2>ملخص المركز المالي</h2>
                <p>الأصول والالتزامات وحقوق الملكية خلال الفترة المحددة.</p>
            </div>
        </div>
        <div class="sw-table-wrap">
            <table class="sw-table accounting-summary-table">
                <tbody>
                    <tr><th>الأصول</th><td>{{ $assets }}</td></tr>
                    <tr><th>الالتزامات</th><td>{{ $liabilities }}</td></tr>
                    <tr><th>حقوق الملكية المرحلة</th><td>{{ $equity }}</td></tr>
                    <tr><th>ربح/خسارة الفترة الحالية (عرض فقط)</th><td>{{ $current_profit }}</td></tr>
                    <tr><th>الالتزامات وحقوق الملكية</th><td>{{ $liabilities_and_equity }}</td></tr>
                    <tr><th>الفرق</th><td>{{ $difference }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    @if($comparison)
        <section class="sw-card accounting-table-card">
            <div class="sw-card__header">
                <div>
                    <h2>المقارنة</h2>
                    <p>مقارنة أرصدة المركز المالي بالفترة المختارة.</p>
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
