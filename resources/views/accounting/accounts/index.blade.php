@extends('layouts.app')
@section('title','دليل الحسابات') @section('page-title','دليل الحسابات')
@section('content')
<div class="sw-page-actions">
@can('create',App\Models\Account::class)<a class="sw-btn" href="{{ route('accounting.accounts.create') }}">إنشاء حساب</a>@endcan
@if(auth()->user()->hasPermission('accounting.account_groups.view'))<a class="sw-btn sw-btn--secondary" href="{{ route('accounting.groups.index') }}">مجموعات الحسابات</a>@endif
</div>
<form class="sw-card sw-form" method="GET"><div class="sw-form-grid">
<label>بحث<input name="search" value="{{ request('search') }}"></label>
<label>النوع<select name="account_type_id"><option value="">الكل</option>@foreach($types as $type)<option value="{{ $type->id }}" @selected(request('account_type_id')==$type->id)>{{ $type->name_ar }}</option>@endforeach</select></label>
<label>المجموعة<select name="account_group_id"><option value="">الكل</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(request('account_group_id')==$group->id)>{{ $group->name_ar }}</option>@endforeach</select></label>
<label>الحالة<select name="is_active"><option value="">الكل</option><option value="1">فعال</option><option value="0">معطل</option></select></label>
</div><button class="sw-btn">تصفية</button></form>
<div class="sw-card"><h3>List View</h3><table class="sw-table"><thead><tr><th>الكود</th><th>الاسم</th><th>النوع</th><th>المجموعة</th><th>المستوى</th><th>الطبيعة</th><th>Header/Posting</th><th>الحالة</th></tr></thead><tbody>
@forelse($accounts as $account)<tr><td><a href="{{ route('accounting.accounts.edit',$account) }}">{{ $account->account_code }}</a></td><td>{{ $account->name_ar }}</td><td>{{ $account->type->name_ar }}</td><td>{{ $account->group?->name_ar }}</td><td>{{ $account->account_level }}</td><td>{{ $account->normal_balance }}</td><td>{{ $account->is_header?'Header':'Posting' }}</td><td>{{ $account->is_active?'فعال':'معطل' }}</td></tr>@empty<tr><td colspan="8">لا توجد حسابات.</td></tr>@endforelse
</tbody></table>{{ $accounts->links() }}</div>
<div class="sw-card"><h3>Tree View</h3>
@forelse($tree as $root)<div style="margin-inline-start:{{ $root->account_level*1.25 }}rem">{{ $root->account_code }} — {{ $root->name_ar }}
@foreach($root->children as $child)<div style="margin-inline-start:1.25rem">{{ $child->account_code }} — {{ $child->name_ar }}
@foreach($child->children as $leaf)<div style="margin-inline-start:1.25rem">{{ $leaf->account_code }} — {{ $leaf->name_ar }}</div>@endforeach
</div>@endforeach</div>@empty<p>لا توجد شجرة حسابات.</p>@endforelse
</div>
@endsection
