@extends('layouts.app')
@section('title','الميزانية العمومية') @section('page-title','الميزانية العمومية')
@section('content') @include('accounting.reports._filters',['allowComparative'=>true])
<div class="sw-alert">{{ $balanced ? 'Accounting equation is balanced.' : 'Accounting equation difference: '.$difference }}</div>
<div class="sw-card"><table class="sw-table"><tbody><tr><th>الأصول</th><td>{{ $assets }}</td></tr><tr><th>الالتزامات</th><td>{{ $liabilities }}</td></tr><tr><th>حقوق الملكية المرحلة</th><td>{{ $equity }}</td></tr><tr><th>ربح/خسارة الفترة الحالية (عرض فقط)</th><td>{{ $current_profit }}</td></tr><tr><th>الالتزامات وحقوق الملكية</th><td>{{ $liabilities_and_equity }}</td></tr><tr><th>الفرق</th><td>{{ $difference }}</td></tr></tbody></table></div>
@if($comparison)<div class="sw-card"><table class="sw-table"><thead><tr><th>البند</th><th>الحالي</th><th>المقارن</th><th>الفرق</th><th>%</th></tr></thead><tbody>@foreach($comparison as $name=>$values)<tr><td>{{ $name }}</td><td>{{ $values['current'] }}</td><td>{{ $values['previous'] }}</td><td>{{ $values['difference'] }}</td><td>{{ $values['percentage'] ?? 'N/A' }}</td></tr>@endforeach</tbody></table></div>@endif
@endsection
