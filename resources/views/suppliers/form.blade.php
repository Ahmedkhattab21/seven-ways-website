@extends('layouts.app')
@section('title',$supplier->exists?'تعديل المورد':'إضافة مورد') @section('page-title',$supplier->exists?'تعديل المورد':'إضافة مورد')
@section('content')
<form class="sw-card sw-form" method="POST" action="{{ $supplier->exists?route('suppliers.update',$supplier):route('suppliers.store') }}">@csrf @if($supplier->exists) @method('PUT') @endif
<div class="sw-form-grid">
<label>الاسم<input name="name" required value="{{ old('name',$supplier->name) }}"></label>
<label>الاسم القانوني<input name="legal_name" value="{{ old('legal_name',$supplier->legal_name) }}"></label>
<label>النوع<select name="supplier_type">@foreach(['manufacturer','distributor','wholesaler','service_provider','other'] as $type)<option value="{{ $type }}" @selected(old('supplier_type',$supplier->supplier_type)===$type)>{{ $type }}</option>@endforeach</select></label>
<label>الرقم الضريبي<input name="tax_number" value="{{ old('tax_number',$supplier->tax_number) }}"></label>
<label>السجل التجاري<input name="commercial_registration" value="{{ old('commercial_registration',$supplier->commercial_registration) }}"></label>
<label>البريد<input type="email" name="email" value="{{ old('email',$supplier->email) }}"></label>
<label>الهاتف<input name="phone" value="{{ old('phone',$supplier->phone) }}"></label>
<label>العملة<select name="currency_id"><option value="">عملة الشركة</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected(old('currency_id',$supplier->currency_id)==$currency->id)>{{ $currency->code }}</option>@endforeach</select></label>
<label>أيام السداد<input type="number" min="0" name="payment_terms_days" value="{{ old('payment_terms_days',$supplier->payment_terms_days??0) }}"></label>
<label>حد الائتمان<input type="number" step=".0001" min="0" name="credit_limit" value="{{ old('credit_limit',$supplier->credit_limit) }}"></label>
</div><label>ملاحظات<textarea name="notes">{{ old('notes',$supplier->notes) }}</textarea></label><button class="sw-btn">حفظ</button>
</form>
@endsection
