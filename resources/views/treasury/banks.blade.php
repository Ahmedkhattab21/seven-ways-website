@extends('layouts.app')
@section('title', 'البنوك')
@section('page-title', 'دليل البنوك')
@section('content')
@if(auth()->user()->hasPermission('treasury.banks.manage'))
<form class="sw-card" method="POST" action="{{ route('treasury.banks.store') }}">
    @csrf
    <h3>إضافة بنك خاص بالشركة</h3>
    <div class="sw-form-grid"><input name="code" required placeholder="الكود"><input name="name_ar" required placeholder="الاسم العربي"><input name="name_en" placeholder="الاسم الإنجليزي"><input name="swift_code" placeholder="SWIFT"><input name="website" placeholder="Website"></div>
    <button class="sw-btn">حفظ</button>
</form>
@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الكود</th><th>البنك</th><th>النطاق</th><th>الحالة</th></tr></thead><tbody>
@foreach($banks as $bank)<tr><td>{{ $bank->code }}</td><td>{{ $bank->name_ar }}</td><td>{{ $bank->is_system ? 'System' : 'Company' }}</td><td>{{ $bank->is_active ? 'نشط' : 'معطل' }}</td></tr>@endforeach
</tbody></table></div>
@endsection
