@extends('layouts.app')

@section('title', 'لوحة مؤشرات الفروع')
@section('page-title', 'لوحة مؤشرات الفروع')
@section('page-description', 'المقارنة محصورة في الفروع التي يملك المستخدم صلاحية رؤيتها.')

@section('content')
    <form method="GET" class="sw-report-filters">
        <label><span>الفرع</span><select name="branch_id">
            @if(auth()->user()->hasPermission('reports.view_all_branches'))<option value="">كل الفروع المسموحة</option>@endif
            @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int) request('branch_id') === $branch->id)>{{ $branch->name }}</option>@endforeach
        </select></label>
        <label><span>من</span><input type="date" name="date_from" value="{{ request('date_from', now()->startOfMonth()->toDateString()) }}"></label>
        <label><span>إلى</span><input type="date" name="date_to" value="{{ request('date_to', now()->toDateString()) }}"></label>
        <button class="sw-button sw-button--primary" type="submit">تطبيق</button>
    </form>
    @php($money = app(\App\Services\MoneyFormatter::class))
    @foreach($dashboards as $item)
        @php($data = $item['data']['current'])
        <x-card :title="$item['branch']->name" subtitle="بيانات الفرع فقط">
            <div class="sw-grid sw-grid-3">
                <div class="sw-metric"><span>صافي المبيعات</span><strong>{{ $money->format($data['sales']['net_sales_before_tax'], auth()->user()->company->currency) }}</strong></div>
                <div class="sw-metric"><span>التحصيلات المستحقة</span><strong>{{ $money->format($data['receivables']['outstanding'], auth()->user()->company->currency) }}</strong></div>
                <div class="sw-metric"><span>الموردون</span><strong>{{ $money->format($data['payables']['outstanding'], auth()->user()->company->currency) }}</strong></div>
                <div class="sw-metric"><span>قيمة المخزون</span><strong>{{ $money->format($data['inventory']['stock_valuation'], auth()->user()->company->currency) }}</strong></div>
                <div class="sw-metric"><span>النقدية والبنوك</span><strong>{{ $money->format($data['treasury']['total_cash_and_bank'], auth()->user()->company->currency) }}</strong></div>
                <div class="sw-metric"><span>اعتمادات معلقة</span><strong>{{ $data['approvals']['pending'] }}</strong></div>
            </div>
        </x-card>
    @endforeach
@endsection

