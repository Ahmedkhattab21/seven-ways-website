@extends('layouts.app')
@section('title', 'لوحة المحاسبة')
@section('page-title', 'لوحة المحاسبة')
@section('page-description', 'الأرقام المالية المرحلة والتنبيهات التشغيلية غير المرحلة، كلٌ على حدة.')

@section('content')
@php
    $company = auth()->user()->company;
    $money = app(\App\Services\MoneyFormatter::class);
    $currency = $currencies->firstWhere('id', request('currency_id', $company->currency_id)) ?: $company->currency;
    $posted = $dashboard['posted'];
    $cards = [
        ['إجمالي المدين المرحّل', $posted['period_debit'], $posted['trial_balance_balanced'] ? 'القيود متوازنة' : 'يوجد فرق يحتاج مراجعة', 'chart'],
        ['إجمالي الدائن المرحّل', $posted['period_credit'], $posted['trial_balance_balanced'] ? 'القيود متوازنة' : 'يوجد فرق يحتاج مراجعة', 'chart'],
        ['إيرادات مرحلة', $posted['revenue'], 'من القيود المرحلة فقط', 'trend'],
        ['مصروفات مرحلة', $posted['expenses'], 'من القيود المرحلة فقط', 'wallet'],
        ['نتيجة التشغيل المرحلة', $posted['estimated_operating_result'], 'إيرادات ناقص مصروفات', 'chart'],
        ['أرصدة العملاء', $dashboard['receivables']['outstanding'], 'رصيد تشغيلي حالي', 'users'],
        ['أرصدة الموردين', $dashboard['payables']['outstanding'], 'رصيد تشغيلي حالي', 'clipboard'],
        ['النقدية والبنوك الدفترية', bcadd($dashboard['treasury']['cash_book_balance'], $dashboard['treasury']['bank_book_balance'], 4), 'من القيود المرحلة', 'wallet'],
    ];
@endphp

<form method="GET" class="sw-filter-panel">
    <div class="sw-form-grid">
        <label>من<input type="date" name="date_from" value="{{ $filters->dateFrom }}"></label>
        <label>إلى<input type="date" name="date_to" value="{{ $filters->dateTo }}"></label>
        <label>الفرع<select name="branch_id"><option value="">الفروع المتاحة</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(count($filters->branchIds) === 1 && $filters->branchIds[0] === $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
        <label>العملة<select name="currency_id">@foreach($currencies as $item)<option value="{{ $item->id }}" @selected($currency?->id === $item->id)>{{ $item->code }}</option>@endforeach</select></label>
    </div>
    <button class="sw-btn sw-btn--primary" type="submit">تطبيق</button>
</form>

<div class="sw-analytics-period">الفترة: {{ $dashboard['period'][0] }} — {{ $dashboard['period'][1] }}<span>الفترة المحاسبية: {{ $dashboard['current_period']->name ?? 'لا توجد فترة حالية' }}</span></div>
<div class="sw-stats-grid">
    @foreach($cards as [$label, $value, $hint, $icon])<x-stat-card :label="$label" :value="$money->format($value, $currency, 'ar', $company)" :hint="$hint" :icon="$icon" />@endforeach
    <x-stat-card label="مصادر غير مرحلة" :value="number_format($dashboard['unposted_count'])" hint="لا تدخل في المؤشرات المالية أعلاه" icon="alert" />
</div>

<div class="sw-dashboard-grid">
    <x-card title="حالة القيود" subtitle="ضمن الفترة والنطاق المختارين"><div class="sw-analytics-list"><p><span>مسودة</span><strong>{{ $dashboard['journals']['draft'] }}</strong></p><p><span>بانتظار الإجراء</span><strong>{{ $dashboard['journals']['pending'] }}</strong></p><p><span>مرحلة</span><strong>{{ $dashboard['journals']['posted'] }}</strong></p></div></x-card>
    <x-card title="مصادر تشغيلية غير مرحلة" subtitle="تنبيه منفصل عن الأرقام المالية">
        @forelse($dashboard['unposted'] as $source)<div class="sw-analytics-list"><p><span>{{ $source['source_number'] }}</span><strong>{{ $money->format($source['amount'] ?? 0, $currency, 'ar', $company) }}</strong></p></div>@empty<x-empty-state title="لا توجد مصادر غير مرحلة" message="كل المصادر المؤهلة معالجة محاسبيًا." icon="clipboard" />@endforelse
    </x-card>
    <x-card title="أحدث القيود" subtitle="داخل النطاق المحدد">
        @forelse($dashboard['latest_journals'] as $journal)<div class="sw-analytics-list"><p><span>{{ $journal->journal_number }} — {{ $journal->description }}</span><strong>{{ $journal->status === 'posted' ? 'مرحّل' : 'غير مرحّل' }}</strong></p></div>@empty<x-empty-state title="لا توجد قيود" message="لا توجد قيود ضمن الفترة المحددة." icon="clipboard" />@endforelse
    </x-card>
</div>

@if($dashboard['quickActions'])<x-card title="إجراءات وتقارير"><div class="sw-quick-actions">@foreach($dashboard['quickActions'] as $action)<a href="{{ $action['url'] }}">{{ $action['label'] }}</a>@endforeach</div></x-card>@endif
@endsection
