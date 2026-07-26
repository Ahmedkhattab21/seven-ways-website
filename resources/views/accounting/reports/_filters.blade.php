<form class="sw-card sw-form" method="GET">
<div class="sw-form-grid">
<label>من<input type="date" name="date_from" value="{{ request('date_from') }}"></label>
<label>إلى<input type="date" name="date_to" value="{{ request('date_to') }}"></label>
@isset($accounts)<label>الحساب<select name="account_id"><option value="">الكل</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(request('account_id')==$account->id)>{{ $account->account_code }} — {{ $account->name_ar }}</option>@endforeach</select></label>@endisset
@isset($branches)<label>الفرع<select name="branch_id"><option value="">كل الفروع المتاحة</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(request('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach</select></label>@endisset
@isset($costCenters)<label>مركز التكلفة<select name="cost_center_id"><option value="">الكل</option>@foreach($costCenters as $center)<option value="{{ $center->id }}" @selected(request('cost_center_id')==$center->id)>{{ $center->code }} — {{ $center->name_ar }}</option>@endforeach</select></label>@endisset
@if(!empty($allowTrialOptions))<label>العرض<select name="summary_by"><option value="account">الحسابات</option><option value="group" @selected(request('summary_by')==='group')>المجموعات</option><option value="type" @selected(request('summary_by')==='type')>الأنواع</option></select></label><label><input type="checkbox" name="include_header" value="1" @checked(request()->boolean('include_header'))> إظهار الحسابات الرئيسية</label><label><input type="checkbox" name="include_zero" value="1" @checked(request()->boolean('include_zero'))> إظهار الأرصدة الصفرية</label>@endif
@if(!empty($allowComparative))<label>المقارنة<select name="comparison"><option value="">بدون مقارنة</option><option value="previous_period" @selected(request('comparison')==='previous_period')>الفترة السابقة</option><option value="previous_year" @selected(request('comparison')==='previous_year')>العام السابق</option></select></label>@endif
</div><button class="sw-btn">عرض</button>@if(isset($allowExport) && $allowExport)<button class="sw-btn" name="export" value="csv">CSV</button>@endif
</form>
