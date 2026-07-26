@extends('layouts.app')
@section('title',$account->exists?'تعديل حساب':'إنشاء حساب') @section('page-title',$account->exists?'تعديل حساب':'إنشاء حساب')
@section('content')
<form class="sw-card sw-form" method="POST" action="{{ $account->exists?route('accounting.accounts.update',$account):route('accounting.accounts.store') }}">@csrf @if($account->exists) @method('PUT') @endif
<div class="sw-form-grid">
<label>الكود<input name="account_code" required value="{{ old('account_code',$account->account_code) }}"></label>
<label>الاسم العربي<input name="name_ar" required value="{{ old('name_ar',$account->name_ar) }}"></label>
<label>الاسم الإنجليزي<input name="name_en" value="{{ old('name_en',$account->name_en) }}"></label>
<label>النوع<select name="account_type_id">@foreach($types as $type)<option value="{{ $type->id }}" @selected(old('account_type_id',$account->account_type_id)==$type->id)>{{ $type->name_ar }}</option>@endforeach</select></label>
<label>المجموعة<select name="account_group_id"><option value="">بدون</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('account_group_id',$account->account_group_id)==$group->id)>{{ $group->code }} — {{ $group->name_ar }}</option>@endforeach</select></label>
<label>الحساب الأب<select name="parent_account_id"><option value="">جذر</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_account_id',$account->parent_account_id)==$parent->id)>{{ $parent->account_code }} — {{ $parent->name_ar }}</option>@endforeach</select></label>
<label>الشكل<select name="is_header" onchange="this.form.is_posting.value=this.value==='1'?'0':'1'"><option value="1" @selected(old('is_header',$account->exists?$account->is_header:true))>Header</option><option value="0" @selected(!old('is_header',$account->exists?$account->is_header:true))>Posting</option></select><input type="hidden" name="is_posting" value="{{ old('is_posting',$account->exists?(int)$account->is_posting:0) }}"></label>
<label>العملة<select name="currency_id"><option value="">عملة الشركة</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected(old('currency_id',$account->currency_id)==$currency->id)>{{ $currency->code }}</option>@endforeach</select></label>
<label>Control Type<select name="control_type"><option value="">none</option>@foreach(['accounts_receivable','accounts_payable','inventory','vat_input','vat_output','customer_advances','supplier_advances','employee_advances','fixed_assets','accumulated_depreciation'] as $type)<option value="{{ $type }}" @selected(old('control_type',$account->control_type)===$type)>{{ $type }}</option>@endforeach</select></label>
</div>
@foreach(['allows_multi_currency'=>'متعدد العملات','requires_cost_center'=>'يتطلب مركز تكلفة','requires_branch'=>'يتطلب فرع','requires_customer'=>'يتطلب عميل','requires_supplier'=>'يتطلب مورد','requires_employee'=>'يتطلب موظف','requires_vehicle'=>'يتطلب سيارة','is_control_account'=>'Control Account','is_bank_account'=>'حساب بنكي','is_cash_account'=>'حساب نقدي','is_inventory_account'=>'حساب مخزون','is_tax_account'=>'حساب ضريبة','allow_manual_entry'=>'يسمح بإدخال يدوي'] as $field=>$label)
<label><input type="hidden" name="{{ $field }}" value="0"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field,$account->$field))> {{ $label }}</label>
@endforeach
<label>الوصف<textarea name="description">{{ old('description',$account->description) }}</textarea></label>
<button class="sw-btn">حفظ</button></form>
@endsection
