@extends('layouts.app')
@section('title','مجموعات الحسابات') @section('page-title','مجموعات الحسابات')
@section('content')
<div class="sw-page-actions"><a class="sw-btn sw-btn--secondary" href="{{ route('accounting.account-types.index') }}">أنواع الحسابات</a></div>
@if(auth()->user()->hasPermission('accounting.account_groups.create'))
<form class="sw-card sw-form" method="POST" action="{{ route('accounting.groups.store') }}">@csrf<div class="sw-form-grid">
<label>الكود<input name="code" required></label><label>الاسم<input name="name_ar" required></label>
<label>النوع<select name="account_type_id">@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name_ar }}</option>@endforeach</select></label>
<label>المجموعة الأب<select name="parent_group_id"><option value="">جذر</option>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->code }} — {{ $group->name_ar }}</option>@endforeach</select></label>
</div><button class="sw-btn">إنشاء</button></form>@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الكود</th><th>الاسم</th><th>النوع</th><th>الأب</th><th>المستوى</th><th>الحالة</th></tr></thead><tbody>
@foreach($groups as $group)<tr><td>{{ $group->code }}</td><td>{{ $group->name_ar }}</td><td>{{ $group->type->name_ar }}</td><td>{{ $group->parent?->name_ar }}</td><td>{{ $group->level }}</td><td>{{ $group->is_active?'فعال':'معطل' }}</td></tr>@endforeach
</tbody></table></div>
@endsection
