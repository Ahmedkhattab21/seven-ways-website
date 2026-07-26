@extends('layouts.app')
@section('title','تقارير الفروع') @section('page-title','تقارير الفروع')
@section('content') @include('accounting.reports._filters')
@include('accounting.reports._trial-summary',['report'=>$trial_balance])
@endsection
