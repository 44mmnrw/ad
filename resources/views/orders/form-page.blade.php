@extends('layouts.app')

@php
    $activeMenu = 'orders';
@endphp

@section('title', ($metaTitle ?? 'Заявка') . ' — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="orders-form-page">
        <div class="orders-form-page__top">
            <a class="orders-back" href="{{ $backRoute }}">{{ $backLabel }}</a>
        </div>

        <h1 class="orders-form-page__title">{{ $pageTitle }}</h1>

        @include('orders._form', ['order' => $order])
    </div>
@endsection
