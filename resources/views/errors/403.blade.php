@extends('layouts.guest')

@section('title', 'غير مصرح')
@section('content')
    <x-error-page code="403" title="الوصول غير مسموح" message="ليس لديك الصلاحية اللازمة لعرض هذه الصفحة." />
@endsection
