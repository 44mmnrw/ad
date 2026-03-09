@extends('layouts.app')

@php
    $activeMenu = 'orders';
@endphp

@section('title', 'Заявки — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="orders-list">
        <div class="orders-list__top">
            <h1 class="ad-h1">Все заявки</h1>
            <a class="ad-btn" href="{{ route('orders.create') }}">
                <span aria-hidden="true">
                    <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-doc-plus"></use></svg>
                </span>
                <span>Новая заявка</span>
            </a>
        </div>

        <form class="orders-list__filters" method="get" action="{{ route('orders.index') }}">
            <label class="orders-list__search" for="orders-search">
                <span class="orders-list__search-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-search"></use></svg>
                </span>
                <input id="orders-search" type="search" name="search" value="{{ $search }}" placeholder="Поиск по номеру, маршруту, водителю...">
            </label>

            <div class="orders-list__chips" role="tablist" aria-label="Фильтр по статусу">
                @php
                    $statusOptions = [
                        'all' => 'Все',
                        'new' => 'Новые',
                        'assigned' => 'Назначены',
                        'in_progress' => 'В работе',
                        'completed' => 'Завершено',
                        'cancelled' => 'Отменено',
                    ];
                @endphp

                @foreach ($statusOptions as $value => $label)
                    <button class="ad-btn" type="submit" name="status" value="{{ $value }}">{{ $label }}</button>
                @endforeach
            </div>
        </form>

        <div class="orders-list__table-wrap">
            <table class="orders-list__table" aria-label="Список заявок">
                <thead>
                    <tr>
                        <th>№ Заявки</th>
                        <th>Маршрут</th>
                        <th>Водитель</th>
                        <th>Статус</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <strong class="orders-list__number">{{ $order['number'] }}</strong>
                            </td>
                            <td>
                                <p class="orders-list__main">{{ $order['route']['from']['city'] }} → {{ $order['route']['to']['city'] }}</p>
                                <p class="orders-list__meta">{{ $order['distance'] }}</p>
                            </td>
                            <td>
                                <p class="orders-list__main">{{ $order['driver']['name'] }}</p>
                                <p class="orders-list__meta">{{ $order['driver']['car'] }}</p>
                            </td>
                            <td>
                                <span class="orders-status orders-status--{{ $order['status_code'] }}">{{ $order['status'] }}</span>
                            </td>
                            <td>{{ $order['created_at'] }}</td>
                            <td>
                                <a class="orders-link" href="{{ route('orders.show', $order['id']) }}">
                                    <svg viewBox="0 0 16 16" focusable="false" aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-eye"></use></svg>
                                    <span>Открыть</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="orders-list__empty">Ничего не найдено по текущим фильтрам</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
