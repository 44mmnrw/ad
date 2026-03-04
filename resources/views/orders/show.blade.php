@extends('layouts.app')

@php
    $activeMenu = 'orders';
@endphp

@section('title', 'Заявка ' . $order['number'] . ' — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="order-show">
        <div class="order-show__top">
            <a class="orders-back" href="{{ route('orders.index') }}">← Вернуться к списку</a>
            <a class="orders-btn orders-btn--outline" href="{{ route('orders.edit', $order['id']) }}">✎ Редактировать</a>
        </div>

        <div class="order-show__grid">
            <div class="order-show__left">
                <section class="order-card">
                    <h2>Информация о заявке</h2>
                    <dl class="order-info">
                        <div><dt>№ Заявки:</dt><dd>{{ $order['number'] }}</dd></div>
                        <div><dt>Дата создания:</dt><dd>{{ $order['created_at'] }}</dd></div>
                        <div>
                            <dt>Статус:</dt>
                            <dd><span class="orders-status orders-status--{{ $order['status_code'] }}">{{ $order['status'] }}</span></dd>
                        </div>
                    </dl>
                </section>

                <section class="order-card">
                    <h2>Маршрут</h2>
                    <div class="route-layout">
                        <div class="route-box">
                            <div class="route-box__line"></div>
                            <div class="route-box__point route-box__point--from"></div>
                            <div class="route-box__point route-box__point--to"></div>

                            <div class="route-box__from">
                                <p class="route-box__label">Откуда</p>
                                <p class="route-box__city">{{ $order['route']['from']['city'] }}</p>
                                <p class="route-box__address">{{ $order['route']['from']['address'] }}</p>
                            </div>

                            <div class="route-box__distance">{{ $order['distance'] }}</div>

                            <div class="route-box__to">
                                <p class="route-box__label">Куда</p>
                                <p class="route-box__city">{{ $order['route']['to']['city'] }}</p>
                                <p class="route-box__address">{{ $order['route']['to']['address'] }}</p>
                            </div>
                        </div>

                        @if (!empty($routeMapUrl))
                            <div class="route-map">
                                <img
                                    src="{{ $routeMapUrl }}"
                                    alt="Карта маршрута {{ $order['route']['from']['city'] }} — {{ $order['route']['to']['city'] }}"
                                    loading="lazy"
                                >
                            </div>
                        @else
                            <p class="route-map__fallback">Карта маршрута станет доступна после добавления координат отправления и назначения.</p>
                        @endif
                    </div>
                </section>

                <section class="order-card">
                    <h2>Прогресс доставки</h2>
                    <div class="progress-line">
                        <div class="progress-line__track"></div>
                        @foreach ($order['progress'] as $step)
                            <div class="progress-line__step progress-line__step--{{ $step['state'] }}">
                                <div class="progress-line__dot"></div>
                                <div class="progress-line__title">{{ $step['title'] }}</div>
                                <div class="progress-line__time">{{ $step['time'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="order-card">
                    <h2>Ссылка для водителя</h2>
                    <div class="driver-link-box">
                        <p class="driver-link-box__url">🔗 {{ $order['driver_link'] }}</p>
                        <p class="driver-link-box__hint">{{ $order['driver_link_expires'] }}</p>
                    </div>
                    <div class="order-actions">
                        <button class="orders-btn orders-btn--outline" type="button">📋 Копировать ссылку</button>
                        <button class="orders-btn orders-btn--success" type="button">📱 Отправить SMS</button>
                    </div>
                </section>
            </div>

            <aside class="order-show__right" aria-label="Участники перевозки">
                <section class="person-card person-card--blue">
                    <h3>{{ $order['driver']['emoji'] }} {{ $order['driver']['title'] }}</h3>
                    <p class="person-card__label">ФИО</p>
                    <p class="person-card__value">{{ $order['driver']['name'] }}</p>
                    <p class="person-card__label">Авто</p>
                    <p class="person-card__value">{{ $order['driver']['car'] }}</p>
                    <p class="person-card__label">Телефон</p>
                    <p class="person-card__value">{{ $order['driver']['phone'] }}</p>
                    <div class="person-card__actions person-card__actions--two">
                        <button class="orders-btn orders-btn--primary" type="button">📞 Позвонить</button>
                        <button class="orders-btn orders-btn--success" type="button">💬 WhatsApp</button>
                    </div>
                </section>

                <section class="person-card person-card--green">
                    <h3>{{ $order['sender']['emoji'] }} {{ $order['sender']['title'] }}</h3>
                    <p class="person-card__label">ФИО</p>
                    <p class="person-card__value">{{ $order['sender']['name'] }}</p>
                    <p class="person-card__label">Телефон</p>
                    <p class="person-card__value">{{ $order['sender']['phone'] }}</p>
                    <div class="person-card__actions">
                        <button class="orders-btn orders-btn--success" type="button">📞 Позвонить</button>
                    </div>
                </section>

                <section class="person-card person-card--blue">
                    <h3>{{ $order['receiver']['emoji'] }} {{ $order['receiver']['title'] }}</h3>
                    <p class="person-card__label">ФИО</p>
                    <p class="person-card__value">{{ $order['receiver']['name'] }}</p>
                    <p class="person-card__label">Телефон</p>
                    <p class="person-card__value">{{ $order['receiver']['phone'] }}</p>
                    <div class="person-card__actions">
                        <button class="orders-btn orders-btn--primary" type="button">📞 Позвонить</button>
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
