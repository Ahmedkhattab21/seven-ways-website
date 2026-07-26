@extends('layouts.app')
@section('title','أنواع الحسابات') @section('page-title','أنواع الحسابات')
@section('content')
<div class="sw-alert">الأنواع النظامية مشتركة منطقيًا ومحمية من التعديل.</div>
@if(auth()->user()->hasPermission('accounting.account_types.manage'))
<form class="sw-card sw-form" method="POST" action="{{ route('accounting.account-types.store') }}">@csrf<div class="sw-form-grid">
<label>الكود<input name="code" required></label><label>الاسم العربي<input name="name_ar" required></label><label>الاسم الإنجليزي<input name="name_en"></label>
<label>التصنيف<select name="classification">@foreach(['asset','liability','equity','revenue','expense'] as $value)<option>{{ $value }}</option>@endforeach</select></label>
<label>الطبيعة<select name="normal_balance"><option>debit</option><option>credit</option></select></label>
<label>القائمة<select name="statement_type"><option>balance_sheet</option><option>income_statement</option></select></label>
<label>التدفق النقدي<select name="cash_flow_category">@foreach(['none','operating','investing','financing'] as $value)<option>{{ $value }}</option>@endforeach</select></label>
<input type="hidden" name="is_active" value="1"></div><button class="sw-btn">إنشاء نوع</button></form>
@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الكود</th><th>الاسم</th><th>التصنيف</th><th>الطبيعة</th><th>القائمة</th><th>النطاق</th><th>الحالة</th></tr></thead><tbody>
@foreach($types as $type)<tr><td>{{ $type->code }}</td><td>{{ $type->name_ar }}</td><td>{{ $type->classification }}</td><td>{{ $type->normal_balance }}</td><td>{{ $type->statement_type }}</td><td>{{ $type->company_id?'الشركة':'System' }}</td><td>{{ $type->is_active?'فعال':'معطل' }}</td></tr>@endforeach
</tbody></table></div>
@endsection
