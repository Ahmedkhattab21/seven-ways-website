@extends('layouts.app')
@section('title','تقارير مراكز التكلفة') @section('page-title','تقارير مراكز التكلفة')
@section('content') @include('accounting.reports._filters')
<div class="sw-alert">حركات مطلوبة بلا مركز تكلفة: {{ $unassigned_required_dimensions }}</div>
@include('accounting.reports._trial-summary',['report'=>$trial_balance])
@endsection
