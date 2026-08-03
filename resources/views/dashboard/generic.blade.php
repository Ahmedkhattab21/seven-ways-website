@extends('layouts.app')

@section('title', 'لوحة التحكم')
@section('page-title', 'لوحة التحكم')
@section('page-description', 'نظرة عامة على حسابك في Seven Ways ERP.')

@section('content')
    <x-card title="مرحبًا، {{ auth()->user()->name }}">
        <x-empty-state
            title="لا يوجد فرع تشغيلي محدد لهذا الحساب."
            message="تواصل مع مسؤول النظام لتحديد الفرع عند الحاجة إلى بيانات التشغيل."
            icon="building"
        />
    </x-card>
@endsection
