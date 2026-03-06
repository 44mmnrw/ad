@extends('layouts.app')

@php
    $activeMenu = 'orders';
@endphp

@section('title', 'Заявка ' . $order['number'] . ' — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="order-show">
        <div class="order-show__top">
            <a class="orders-back" href="{{ route('orders.index') }}">← Вернуться к списку</a>
            <a class="ad-btn" href="{{ route('orders.edit', $order['id']) }}">
                <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-edit"></use></svg>
                <span>Редактировать</span>
            </a>
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

                <section class="order-card order-card--cost" aria-label="Стоимость перевозки">
                    @php
                        $costWithVat = (int) ($order['cost'] ?? 0);
                        $vatRate = 20;
                        $costWithoutVat = (int) round($costWithVat / (1 + ($vatRate / 100)));
                        $vatAmount = max($costWithVat - $costWithoutVat, 0);
                    @endphp

                    <div class="order-cost-card__body">
                        <div class="order-cost-card__title-row">
                            <span class="order-cost-card__icon" aria-hidden="true">
                                <svg><use href="/icons/sprite.svg#icon-rubl"></use></svg>
                            </span>
                            <h2 class="order-cost-card__title">Стоимость перевозки</h2>
                        </div>

                        <div class="order-cost">
                            <div class="order-cost__item">
                                <p class="order-cost__label">Без НДС</p>
                                <p class="order-cost__value">{{ number_format($costWithoutVat, 0, ',', ' ') }} ₽</p>
                            </div>
                            <div class="order-cost__item">
                                <p class="order-cost__label">Ставка НДС</p>
                                <p class="order-cost__value">{{ $vatRate }}%</p>
                            </div>
                            <div class="order-cost__item">
                                <p class="order-cost__label">Сумма НДС</p>
                                <p class="order-cost__value order-cost__value--muted">{{ number_format($vatAmount, 0, ',', ' ') }} ₽</p>
                            </div>
                            <div class="order-cost__item">
                                <p class="order-cost__label">Итого с НДС</p>
                                <p class="order-cost__value order-cost__value--total">{{ number_format($costWithVat, 0, ',', ' ') }} ₽</p>
                            </div>
                        </div>
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
                        <button class="ad-btn" type="button">📋 Копировать ссылку</button>
                        <button class="ad-btn" type="button">📱 Отправить SMS</button>
                    </div>
                </section>
            </div>

            <aside class="order-show__right" aria-label="Участники перевозки">
                <section class="person-card person-card--carrier">
                    <div class="person-card__head">
                        <span class="person-card__icon-wrap" aria-hidden="true">
                            <svg><use href="/icons/sprite.svg#icon-truck"></use></svg>
                        </span>
                        <h3>Грузоперевозчик</h3>
                    </div>
                    <p class="person-card__label">Название</p>
                    <p class="person-card__value">ООО "ТрансЛогистик"</p>
                    <p class="person-card__label">Телефон</p>
                    <p class="person-card__value">+7 (495) 100-20-30</p>
                    <div class="person-card__actions">
                        <button class="ad-btn" type="button">
                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg>
                            <span>Позвонить</span>
                        </button>
                    </div>
                </section>

                <section class="person-card person-card--driver">
                    <div class="person-card__head">
                        <span class="person-card__icon-wrap" aria-hidden="true">
                            <svg><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                        </span>
                        <h3>Водитель</h3>
                    </div>
                    <p class="person-card__label">ФИО</p>
                    <p class="person-card__value">{{ $order['driver']['name'] }}</p>
                    <p class="person-card__label">Транспорт</p>
                    <p class="person-card__value">{{ $order['driver']['car'] }}</p>
                    <p class="person-card__label">Телефон</p>
                    <p class="person-card__value">{{ $order['driver']['phone'] }}</p>
                    <div class="person-card__actions person-card__actions--two">
                        <button class="ad-btn" type="button">
                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg>
                            <span>Позвонить</span>
                        </button>
                        <button class="ad-btn" type="button">
                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-message"></use></svg>
                            <span>WhatsApp</span>
                        </button>
                    </div>
                </section>

                <section class="person-card person-card--sender">
                    <div class="person-card__head">
                        <span class="person-card__icon-wrap" aria-hidden="true">
                            <svg><use href="/icons/sprite.svg#icon-doc-package"></use></svg>
                        </span>
                        <h3>Грузоотправитель</h3>
                    </div>
                    <p class="person-card__label">ФИО / Организация</p>
                    <p class="person-card__value">{{ $order['sender']['name'] }}</p>
                    <p class="person-card__label">Телефон</p>
                    <p class="person-card__value">{{ $order['sender']['phone'] }}</p>
                    <div class="person-card__actions">
                        <button class="ad-btn" type="button">
                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg>
                            <span>Позвонить</span>
                        </button>
                    </div>
                </section>

                <section class="person-card person-card--receiver">
                    <div class="person-card__head">
                        <span class="person-card__icon-wrap" aria-hidden="true">
                            <svg><use href="/icons/sprite.svg#icon-doc-packagecheck"></use></svg>
                        </span>
                        <h3>Грузополучатель</h3>
                    </div>
                    <p class="person-card__label">ФИО / Организация</p>
                    <p class="person-card__value">{{ $order['receiver']['name'] }}</p>
                    <p class="person-card__label">Телефон</p>
                    <p class="person-card__value">{{ $order['receiver']['phone'] }}</p>
                    <div class="person-card__actions">
                        <button class="ad-btn" type="button">
                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg>
                            <span>Позвонить</span>
                        </button>
                    </div>
                </section>
            </aside>
        </div>
    </div>
@endsection
