@extends('layouts.app')

@section('title', 'لوحة المؤشرات التنفيذية')
@section('page-title', 'لوحة المؤشرات التنفيذية')
@section('page-description', 'مؤشرات مشتقة لحظيًا من المستندات المعتمدة والقيود المرحلة داخل نطاق صلاحياتك.')

@section('content')
    @include('analytics._filters')
    @php
        $company = auth()->user()->company;
        $currency = $currencies->firstWhere('id', request('currency_id', $company->currency_id)) ?: $company->currency;
        $money = app(\App\Services\MoneyFormatter::class);
        $current = $dashboard['current'];
        $cards = [
            ['صافي المبيعات قبل الضريبة', $current['sales']['net_sales_before_tax'], 'net_sales'],
            ['النتيجة التشغيلية المقدرة', $current['financial']['estimated_operating_result'], 'operating_result'],
            ['أرصدة العملاء', $current['receivables']['outstanding'], 'receivables'],
            ['أرصدة الموردين', $current['payables']['outstanding'], 'payables'],
            ['قيمة المخزون بالتكلفة', $current['inventory']['stock_valuation'], 'inventory'],
            ['النقدية والبنوك دفترية', $current['treasury']['total_cash_and_bank'], 'cash_and_bank'],
        ];
    @endphp
    <div class="sw-analytics-period">
        الفترة الحالية: {{ $dashboard['period'][0] }} — {{ $dashboard['period'][1] }}
        <span>المقارنة: {{ $dashboard['previous_period'][0] }} — {{ $dashboard['previous_period'][1] }}</span>
    </div>
    <div class="sw-stats-grid">
        @foreach($cards as [$label, $value, $comparison])
            @php($change = $dashboard['comparisons'][$comparison])
            <x-stat-card
                :label="$label"
                :value="$money->format($value, $currency, app()->getLocale(), $company)"
                :hint="$change['percentage'] === null ? 'التغير: N/A' : 'التغير: '.number_format($change['percentage'], 2).'%'"
                icon="chart"
            />
        @endforeach
    </div>

    <x-card title="المؤشرات التشغيلية الحالية" subtitle="من المستندات النهائية مباشرة، ولا تشترط الترحيل المحاسبي">
        <div class="sw-stats-grid">
            <x-stat-card label="صافي المبيعات التشغيلي" :value="$money->format($dashboard['operational']['net_sales'], $currency, app()->getLocale(), $company)" hint="الفواتير النهائية ناقص الإشعارات الدائنة" icon="trend" />
            <x-stat-card label="التحصيلات" :value="$money->format($dashboard['operational']['collections'], $currency, app()->getLocale(), $company)" hint="من تحصيلات العملاء فقط" icon="wallet" />
            <x-stat-card label="أوامر الشراء المفتوحة" :value="number_format($dashboard['operational']['open_purchase_orders'])" hint="كل الفروع المختارة" icon="clipboard" />
            <x-stat-card label="تنبيهات المخزون" :value="number_format($dashboard['operational']['low_stock_count'] + $dashboard['operational']['negative_stock_count'])" hint="منخفض أو سالب" icon="alert" />
        </div>
    </x-card>

    <x-card title="مقارنة الفروع" subtitle="نفس الفترة والفلاتر المختارة">
        <div class="sw-table-wrap"><table class="sw-table"><thead><tr><th>الفرع</th><th>صافي المبيعات</th><th>التحصيلات</th><th>المديونية</th><th>قيمة المخزون</th><th>اعتمادات معلقة</th></tr></thead><tbody>
        @foreach($dashboard['branch_comparison'] as $row)<tr><td>{{ $row['branch']->name }}</td><td>{{ $money->format($row['metrics']['net_sales'], $currency, app()->getLocale(), $company) }}</td><td>{{ $money->format($row['metrics']['collections'], $currency, app()->getLocale(), $company) }}</td><td>{{ $money->format($row['metrics']['receivables'], $currency, app()->getLocale(), $company) }}</td><td>{{ $money->format($row['metrics']['inventory_value'], $currency, app()->getLocale(), $company) }}</td><td>{{ $row['metrics']['pending_approvals'] }}</td></tr>@endforeach
        </tbody></table></div>
    </x-card>

    <div class="sw-dashboard-grid">
        <x-card title="اتجاه المبيعات" subtitle="القيم قبل الضريبة حسب الشهر" class="sw-dashboard-grid__chart">
            @if($current['sales_trend'])
                @php($max = max(array_values($current['sales_trend'])) ?: 1)
                <div class="sw-bar-chart" role="img" aria-label="اتجاه المبيعات الشهري">
                    @foreach($current['sales_trend'] as $month => $value)
                        <div class="sw-bar-chart__row">
                            <span>{{ $month }}</span>
                            <div><i style="width: {{ min(100, ($value / $max) * 100) }}%"></i></div>
                            <strong>{{ $money->format($value, $currency, app()->getLocale(), $company) }}</strong>
                        </div>
                    @endforeach
                </div>
            @else
                <x-empty-state title="لا توجد مبيعات مرحلة" message="لا توجد بيانات ضمن الفترة والفروع المختارة." icon="chart" />
            @endif
        </x-card>

        <x-card title="مؤشرات تشغيلية" subtitle="تفاصيل تحتاج متابعة">
            <div class="sw-analytics-list">
                <p><span>فواتير العملاء غير المسددة</span><strong>{{ $current['receivables']['invoice_count'] }}</strong></p>
                <p><span>أصناف تحت حد الطلب</span><strong>{{ $current['inventory']['reorder_items'] }}</strong></p>
                <p><span>تحويلات خزينة معلقة</span><strong>{{ $current['treasury']['pending_transfers'] }}</strong></p>
                <p><span>جلسات صندوق مفتوحة</span><strong>{{ $current['treasury']['open_cash_sessions'] }}</strong></p>
                <p><span>اعتمادات منتظرة</span><strong>{{ $current['approvals']['pending'] }}</strong></p>
                <p><span>اعتمادات متأخرة</span><strong>{{ $current['approvals']['overdue'] }}</strong></p>
            </div>
        </x-card>
    </div>

    <x-card title="مركز التقارير" subtitle="التقارير المتاحة حسب صلاحيات المستخدم">
        <div class="sw-report-links">
            @foreach($reports as $report)
                @if(auth()->user()->hasPermission($report['permission']))
                    <a href="{{ route('analytics.reports.show', $report['code']) }}">
                        <x-icon name="chart" />
                        <span>{{ $report['name'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </x-card>
@endsection
