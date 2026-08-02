@extends('layouts.app')
@section('title','إعدادات المحاسبة') @section('page-title','إعدادات المحاسبة')
@section('content')
<div class="configuration-page">
<div class="sw-alert">الترحيل يظل يدويًا عبر Post to Accounting؛ مفاتيح Auto Post غير مفعلة افتراضيًا.</div>
<form class="sw-card sw-form" method="POST" action="{{ route('accounting.settings.update') }}">@csrf @method('PUT')<h3>الإعدادات العامة وسياسة الفترات والموافقات</h3><div class="sw-form-grid">
<label>العملة الأساسية<select name="base_currency_id">@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected($settings->base_currency_id===$currency->id)>{{ $currency->code }}</option>@endforeach</select></label>
<label>السنة الحالية<select name="current_fiscal_year_id"><option value="">بدون</option>@foreach($years as $year)<option value="{{ $year->id }}" @selected($settings->current_fiscal_year_id===$year->id)>{{ $year->name }}</option>@endforeach</select></label>
<label>دقة التقريب<input type="number" min="0" max="8" name="default_rounding_precision" value="{{ $settings->default_rounding_precision }}"></label>
</div>
@foreach(['allow_manual_journals'=>'السماح بالقيود اليدوية مستقبلًا','require_journal_approval'=>'تتطلب موافقة','enforce_balanced_dimensions'=>'فرض اتزان الأبعاد','enforce_cost_center_on_expense'=>'مركز تكلفة للمصروف','enforce_branch_on_posting'=>'فرض الفرع','allow_posting_to_soft_closed_period'=>'السماح الاستثنائي في Soft Closed','separation_of_duties'=>'فصل المهام'] as $field=>$label)<label><input type="hidden" name="{{ $field }}" value="0"><input type="checkbox" name="{{ $field }}" value="1" @checked($settings->$field)> {{ $label }}</label>@endforeach
<button class="sw-btn">حفظ الإعدادات</button></form>
<div class="sw-card"><h3>حسابات الفروع</h3>
@php
    $accountLabels = [
        'cash_account_id' => 'النقدية',
        'bank_account_id' => 'البنك',
        'accounts_receivable_account_id' => 'العملاء',
        'accounts_payable_account_id' => 'الموردون',
        'sales_revenue_account_id' => 'إيرادات المبيعات',
        'service_revenue_account_id' => 'إيرادات الخدمات',
        'product_revenue_account_id' => 'إيرادات المنتجات',
        'sales_discount_account_id' => 'خصومات المبيعات',
        'sales_return_account_id' => 'مردودات المبيعات',
        'inventory_account_id' => 'المخزون',
        'cost_of_goods_sold_account_id' => 'تكلفة البضاعة المباعة',
        'inventory_adjustment_account_id' => 'تسويات المخزون',
        'purchase_account_id' => 'المشتريات',
        'purchase_return_account_id' => 'مردودات المشتريات',
        'vat_input_account_id' => 'ضريبة المدخلات',
        'vat_output_account_id' => 'ضريبة المخرجات',
        'customer_advance_account_id' => 'دفعات مقدمة من العملاء',
        'supplier_advance_account_id' => 'دفعات مقدمة للموردين',
        'rounding_account_id' => 'فروق التقريب',
    ];
@endphp
@foreach($branches as $branch)<form class="sw-form" method="POST" action="{{ route('accounting.branch-settings.update',$branch) }}">@csrf @method('PUT')<h4>{{ $branch->name }}</h4><div class="sw-form-grid">
<label>مركز التكلفة<select name="default_cost_center_id"><option value="">بدون</option>@foreach($costCenters as $center)<option value="{{ $center->id }}" @selected(optional($mappings->get($branch->id))->default_cost_center_id===$center->id)>{{ $center->code }} — {{ $center->name_ar }}</option>@endforeach</select></label>
@foreach($accountColumns as $field)
<label>{{ $accountLabels[$field] }}<select name="{{ $field }}"><option value="">بدون</option>@foreach($accountOptions[$field] as $account)<option value="{{ $account->id }}" @selected(optional($mappings->get($branch->id))->$field===$account->id)>{{ $account->account_code }} — {{ $account->name_ar }}@if($account->control_type) — {{ $account->control_type }}@endif</option>@endforeach</select></label>
@endforeach
@if($accountOptions['customer_advance_account_id']->isEmpty())
<div class="sw-alert sw-alert--warning">لا يوجد حساب حركة نشط من نوع "دفعات مقدمة من العملاء". أنشئ الحساب أولًا من دليل الحسابات.</div>
@endif
</div><button class="sw-btn">حفظ ربط الفرع</button></form>@endforeach
</div>
</div>
@endsection
