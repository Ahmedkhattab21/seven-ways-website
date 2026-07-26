@extends('layouts.app')
@section('title','مراكز التكلفة') @section('page-title','مراكز التكلفة')
@section('content')
@if(auth()->user()->hasPermission('accounting.cost_centers.create'))<form class="sw-card sw-form" method="POST" action="{{ route('accounting.cost-centers.store') }}">@csrf<div class="sw-form-grid">
<label>الكود<input name="code" required></label><label>الاسم<input name="name_ar" required></label>
<label>الفرع<select name="branch_id"><option value="">مستوى الشركة</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label>
<label>الأب<select name="parent_cost_center_id"><option value="">جذر</option>@foreach($centers->where('is_header',true) as $center)<option value="{{ $center->id }}">{{ $center->name_ar }}</option>@endforeach</select></label>
<label>النوع<select name="cost_center_type">@foreach(['company','branch','department','workshop','warehouse','sales','marketing','project','administration','other'] as $type)<option>{{ $type }}</option>@endforeach</select></label>
<label>الشكل<select name="is_header" onchange="this.form.is_posting.value=this.value==='1'?'0':'1'"><option value="1">Header</option><option value="0">Posting</option></select><input type="hidden" name="is_posting" value="0"></label>
</div><button class="sw-btn">إنشاء</button></form>@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الكود</th><th>الاسم</th><th>الفرع</th><th>الأب</th><th>المستوى</th><th>النوع</th><th>الحالة</th></tr></thead><tbody>
@foreach($centers as $center)<tr><td>{{ $center->code }}</td><td>{{ $center->name_ar }}</td><td>{{ $center->branch?->name??'الشركة' }}</td><td>{{ $center->parent?->name_ar }}</td><td>{{ $center->level }}</td><td>{{ $center->is_header?'Header':'Posting' }}</td><td>{{ $center->is_active?'فعال':'معطل' }}</td></tr>@endforeach
</tbody></table></div>
@endsection
