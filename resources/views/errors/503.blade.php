@extends('layouts.guest')

@section('title', 'النظام غير متاح')
@section('content')
    <x-error-page code="503" title="النظام تحت الصيانة" message="نعمل على إعادة الخدمة في أقرب وقت ممكن." />
@endsection
