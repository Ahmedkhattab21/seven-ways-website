@extends('layouts.app')
@section('title',$title) @section('page-title',$title)
@section('content')
<div class="sw-page-actions"><a class="sw-btn" href="{{ route($createRoute) }}">إضافة</a></div>
<div class="sw-card"><table class="sw-table"><thead><tr><th>الرقم</th><th>التاريخ</th><th>الطرف/الفرع</th><th>الإجمالي</th><th>الحالة</th></tr></thead><tbody>
@forelse($documents as $document)<tr><td><a href="{{ route($showRoute,$document) }}">{{ data_get($document,$numberField) }}</a></td><td>{{ data_get($document,$dateField) }}</td><td>{{ data_get($document,'supplier.name')??data_get($document,'branch.name') }}</td><td>{{ data_get($document,'total')??data_get($document,'estimated_total') }}</td><td>{{ $document->status }}</td></tr>@empty<tr><td colspan="5">لا توجد مستندات.</td></tr>@endforelse
</tbody></table>{{ $documents->links() }}</div>
@endsection
