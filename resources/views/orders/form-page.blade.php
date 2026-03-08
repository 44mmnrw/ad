@extends('layouts.app')

@php
    $activeMenu = 'orders';
@endphp

@section('title', ($metaTitle ?? 'Заявка') . ' — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="orders-form-page">
        <div class="orders-form-page__top">
            <a class="orders-back" href="{{ $backRoute }}">
                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-left-arrow"></use></svg>
                <span>{{ str_replace('← ', '', $backLabel) }}</span>
            </a>
        </div>

        @include('orders._form', ['order' => $order, 'counterparties' => $counterparties ?? []])
    </div>
@endsection
