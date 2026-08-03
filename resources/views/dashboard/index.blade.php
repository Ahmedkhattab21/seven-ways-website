@extends('layouts.app')

@section('title', 'لوحة تشغيل الفرع')
@section('page-title', 'لوحة تشغيل الفرع')
@section('page-description', 'مؤشرات تشغيلية فعلية للفرع الحالي فقط.')

@section('content')
    @php
        $company = auth()->user()->company;
        $money = app(\App\Services\MoneyFormatter::class);
        $metrics = $dashboard['metrics'];
        $statusLabels = ['draft' => 'مسودة', 'pending_approval' => 'بانتظار الاعتماد', 'approved' => 'معتمدة', 'issued' => 'صادرة', 'partially_paid' => 'مدفوعة جزئيًا', 'paid' => 'مدفوعة', 'overdue' => 'متأخرة', 'credited' => 'مردودة بالكامل', 'posted' => 'مرحلة', 'counting' => 'جارٍ الجرد', 'allocated' => 'مخصصة', 'partially_allocated' => 'مخصصة جزئيًا'];
        $cards = [
            ['صافي مبيعات اليوم', $money->format($metrics['net_sales'], null, 'ar', $company), 'بعد خصم الإشعارات الدائنة', 'trend'],
            ['فواتير اليوم', number_format($metrics['invoice_count']), 'فواتير نهائية', 'clipboard'],
            ['تحصيلات اليوم', $money->format($metrics['collections'], null, 'ar', $company), 'من تحصيلات العملاء فقط', 'wallet'],
            ['مديونية العملاء', $money->format($metrics['receivables'], null, 'ar', $company), 'الرصيد المستحق الحالي', 'users'],
            ['قيمة المخزون', $money->format($metrics['inventory_value'], null, 'ar', $company), 'بمتوسط التكلفة', 'box'],
            ['تنبيهات المخزون', number_format($metrics['low_stock_count'] + $metrics['negative_stock_count']), 'منخفض أو سالب', 'alert'],
            ['أوامر شراء مفتوحة', number_format($metrics['open_purchase_orders']), 'داخل الفرع الحالي', 'clipboard'],
            ['اعتمادات معلقة', number_format($metrics['pending_approvals']), 'تحتاج إجراء', 'clock'],
        ];
    @endphp

    <section class="sw-welcome-banner">
        <div><span class="sw-eyebrow">{{ $dashboard['branch']->code }}</span><h2>أهلًا، {{ auth()->user()->name }}</h2><p>{{ $dashboard['branch']->name }} — بيانات اليوم {{ $dashboard['period'][0] }}</p></div>
    </section>

    <div class="sw-stats-grid">
        @foreach($cards as [$label, $value, $hint, $icon])
            <x-stat-card :label="$label" :value="$value" :hint="$hint" :icon="$icon" />
        @endforeach
    </div>

    <div class="sw-dashboard-grid">
        <x-card title="اتجاه صافي المبيعات" subtitle="آخر 7 أيام" class="sw-dashboard-grid__chart">
            @php($max = max(1, ...array_map(fn ($row) => abs((float) $row['net']), $dashboard['trend'])))
            <div class="sw-bar-chart">
                @foreach($dashboard['trend'] as $row)
                    <div class="sw-bar-chart__row"><span>{{ \Carbon\Carbon::parse($row['date'])->format('d/m') }}</span><div><i style="width: {{ min(100, (abs((float) $row['net']) / $max) * 100) }}%"></i></div><strong>{{ $money->format($row['net'], null, 'ar', $company) }}</strong></div>
                @endforeach
            </div>
        </x-card>

        <x-card title="الخزينة" subtitle="حالة الخزينة في الفرع الحالي">
            @if($metrics['cash_book_balance'] !== null)
                <div class="sw-analytics-list">
                    <p><span>الرصيد الدفتري</span><strong>{{ $money->format($metrics['cash_book_balance'], null, 'ar', $company) }}</strong></p>
                    <p><span>الجلسة النشطة</span><strong>{{ $metrics['cash_session']->session_number ?? 'لا توجد' }}</strong></p>
                    <p><span>الحالة</span><strong>{{ $statusLabels[$metrics['cash_session']->status ?? ''] ?? ($metrics['cash_session']->status ?? '—') }}</strong></p>
                </div>
            @else
                <x-empty-state title="بيانات الخزينة غير متاحة" message="لا توجد خزينة نشطة أو لا تملك صلاحية عرضها." icon="wallet" />
            @endif
        </x-card>

        <x-card title="آخر الأنشطة" subtitle="أحدث مستندات الفرع">
            @forelse($dashboard['activities'] as $activity)
                <div class="sw-analytics-list"><p><span>{{ $activity['label'] }} — {{ $activity['number'] }}</span><strong>{{ $statusLabels[$activity['status']] ?? $activity['status'] }}</strong></p></div>
            @empty
                <x-empty-state title="لا توجد أنشطة" message="لا توجد مستندات مسجلة في الفرع حتى الآن." icon="clock" />
            @endforelse
        </x-card>

        <x-card title="تنبيهات التشغيل" subtitle="حالات تحتاج متابعة">
            @forelse($dashboard['alerts'] as $alert)<div class="sw-alert">{{ $alert['text'] }}</div>@empty
                <x-empty-state title="لا توجد تنبيهات" message="لا توجد حالات تشغيلية تحتاج تدخلًا حاليًا." icon="bell" />
            @endforelse
        </x-card>
    </div>

    @if($dashboard['quickActions'])
        <x-card title="إجراءات سريعة" subtitle="الإجراءات المتاحة حسب صلاحياتك">
            <div class="sw-quick-actions">@foreach($dashboard['quickActions'] as $action)<a href="{{ $action['url'] }}">{{ $action['label'] }}</a>@endforeach</div>
        </x-card>
    @endif
@endsection
