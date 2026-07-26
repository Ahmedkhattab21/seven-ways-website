@extends('layouts.app')
@section('title','قائمة الدخل') @section('page-title','قائمة الدخل')
@section('content') @include('accounting.reports._filters',['allowComparative'=>true])
<div class="sw-card"><table class="sw-table"><tbody><tr><th>صافي الإيراد</th><td>{{ $revenue }}</td></tr><tr><th>تكلفة المبيعات</th><td>{{ $cost_of_sales }}</td></tr><tr><th>مجمل الربح</th><td>{{ $gross_profit }}</td></tr><tr><th>مصروفات التشغيل</th><td>{{ $operating_expenses }}</td></tr><tr><th>ربح التشغيل</th><td>{{ $operating_profit }}</td></tr><tr><th>صافي الربح</th><td>{{ $net_profit }}</td></tr><tr><th>Gross Margin</th><td>{{ $gross_margin ?? 'N/A' }}%</td></tr><tr><th>Net Margin</th><td>{{ $net_margin ?? 'N/A' }}%</td></tr></tbody></table></div>
@if($comparison)<div class="sw-card"><table class="sw-table"><thead><tr><th>البند</th><th>الحالي</th><th>المقارن</th><th>الفرق</th><th>%</th></tr></thead><tbody>@foreach($comparison as $name=>$values)<tr><td>{{ $name }}</td><td>{{ $values['current'] }}</td><td>{{ $values['previous'] }}</td><td>{{ $values['difference'] }}</td><td>{{ $values['percentage'] ?? 'N/A' }}</td></tr>@endforeach</tbody></table></div>@endif
@endsection
