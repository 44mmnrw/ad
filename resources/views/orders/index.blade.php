@extends('layouts.app')

@php
    $activeMenu = 'orders';
@endphp

@section('title', 'Заявки — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="orders-list">
        <div class="orders-list__top">
            <h1 class="orders-list__title">Все заявки</h1>
            <a class="orders-btn orders-btn--success" href="{{ route('orders.create') }}">
                <span aria-hidden="true">＋</span>
                <span>Новая заявка</span>
            </a>
        </div>

        <form class="orders-list__filters" method="get" action="{{ route('orders.index') }}">
            <label class="orders-list__search" for="orders-search">
                <span class="orders-list__search-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" focusable="false">
                        <path d="M9 3a6 6 0 1 1 0 12 6 6 0 0 1 0-12Zm0 1.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Zm5.56 9.99 2.97 2.97a.75.75 0 0 1-1.06 1.06l-2.97-2.97a.75.75 0 1 1 1.06-1.06Z" fill="currentColor"/>
                    </svg>
                </span>
                <input id="orders-search" type="search" name="search" value="{{ $search }}" placeholder="Поиск по номеру, маршруту, водителю...">
            </label>

            <div class="orders-list__chips" role="tablist" aria-label="Фильтр по статусу">
                @php
                    $statusOptions = [
                        'all' => 'Все',
                        'loading' => 'Загрузка',
                        'in_transit' => 'В пути',
                        'unloading' => 'Разгрузка',
                        'completed' => 'Завершено',
                    ];
                @endphp

                @foreach ($statusOptions as $value => $label)
                    <button class="orders-chip {{ $statusFilter === $value ? 'is-active' : '' }}" type="submit" name="status" value="{{ $value }}">{{ $label }}</button>
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
                                    <svg viewBox="0 0 20 20" focusable="false" aria-hidden="true">
                                        <path d="M10 4c3.53 0 6.6 2.16 8 5.24a.85.85 0 0 1 0 .72C16.6 13.04 13.53 15.2 10 15.2S3.4 13.04 2 9.96a.85.85 0 0 1 0-.72C3.4 6.16 6.47 4 10 4Zm0 1.6c-2.76 0-5.2 1.62-6.43 4 .47.92 1.12 1.75 1.92 2.41a7.65 7.65 0 0 0 9.02 0 8.2 8.2 0 0 0 1.92-2.4C15.2 7.2 12.76 5.6 10 5.6Zm0 1.8a2.6 2.6 0 1 1 0 5.2 2.6 2.6 0 0 1 0-5.2Zm0 1.6a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z" fill="currentColor"/>
                                    </svg>
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
