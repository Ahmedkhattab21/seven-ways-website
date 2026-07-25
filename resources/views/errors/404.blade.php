@extends('layouts.guest')

@section('title', 'الصفحة غير موجودة')
@section('content')
    <x-error-page code="404" title="لم نجد هذه الصفحة" message="قد يكون الرابط غير صحيح أو تم نقل الصفحة إلى مكان آخر." />
@endsection
