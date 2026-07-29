@extends('layouts.app')
@section('title','قوالب الترحيل') @section('page-title','قوالب الترحيل')
@section('content')
<div class="configuration-page">
<div class="sw-alert">القالب النشط يحدد سياسة الترحيل، والتنفيذ يتم صراحة من المستند التشغيلي.</div>
@if(auth()->user()->hasPermission('accounting.posting_profiles.create'))<form class="sw-card sw-form" method="POST" action="{{ route('accounting.posting-profiles.store') }}">@csrf<div class="sw-form-grid">
<label>الكود<input name="code" required></label><label>الاسم<input name="name" required></label>
<label>المصدر<select name="source_type">@foreach(App\Services\PostingProfileValidationService::SOURCE_TYPES as $type)<option>{{ $type }}</option>@endforeach</select></label>
<label><input type="hidden" name="is_default" value="0"><input type="checkbox" name="is_default" value="1"> افتراضي</label>
</div>
@foreach(['debit','credit'] as $index=>$side)<div class="sw-form-grid"><input type="hidden" name="lines[{{ $index }}][entry_side]" value="{{ $side }}"><input type="hidden" name="lines[{{ $index }}][account_source]" value="fixed_account"><input type="hidden" name="lines[{{ $index }}][amount_source]" value="total"><input type="hidden" name="lines[{{ $index }}][tax_component]" value="none">
<label>{{ $side }} Account<select name="lines[{{ $index }}][fixed_account_id]">@foreach($accounts as $account)<option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select></label></div>@endforeach
<button class="sw-btn">إنشاء Version</button></form>@endif
<div class="sw-card"><table class="sw-table"><thead><tr><th>الكود</th><th>الاسم</th><th>المصدر</th><th>Version</th><th>الحالة</th><th>Lines</th><th>الإجراء</th></tr></thead><tbody>
@foreach($profiles as $profile)<tr><td>{{ $profile->code }}</td><td>{{ $profile->name }}</td><td>{{ $profile->source_type }}</td><td>{{ $profile->version }}</td><td>{{ $profile->status }}</td><td>{{ $profile->lines->count() }}</td><td>
@if($profile->status==='draft')<form method="POST" action="{{ route('accounting.posting-profiles.action',[$profile,'activate']) }}">@csrf<button class="sw-btn">Activate</button></form>@endif
@if($profile->status==='active')<form method="POST" action="{{ route('accounting.posting-profiles.action',[$profile,'supersede']) }}">@csrf<button class="sw-btn">Supersede</button></form>@endif
</td></tr>@endforeach
</tbody></table></div>
</div>
@endsection
