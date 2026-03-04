@extends('layouts.app')

@php
    $activeMenu = 'dashboard';
@endphp

@section('title', 'Дашборд — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <h1 class="ad-main__title">Дашборд</h1>

    <section class="ad-stats" aria-label="Ключевые показатели">
        <article class="ad-stat-card">
            <div class="ad-stat-card__row">
                <div class="ad-stat-card__badge is-blue">🚚</div>
                <span class="ad-stat-card__trend">↗</span>
            </div>
            <p class="ad-stat-card__value">12</p>
            <p class="ad-stat-card__label">Активных рейсов</p>
        </article>

        <article class="ad-stat-card">
            <div class="ad-stat-card__row">
                <div class="ad-stat-card__badge is-green">📊</div>
                <span class="ad-stat-card__trend">↗</span>
            </div>
            <p class="ad-stat-card__value">28</p>
            <p class="ad-stat-card__label">Завершено сегодня</p>
        </article>

        <article class="ad-stat-card">
            <div class="ad-stat-card__row">
                <div class="ad-stat-card__badge is-blue">👥</div>
            </div>
            <p class="ad-stat-card__value">45</p>
            <p class="ad-stat-card__label">Водителей в работе</p>
        </article>

        <article class="ad-stat-card">
            <div class="ad-stat-card__row">
                <div class="ad-stat-card__badge is-green">🧾</div>
            </div>
            <p class="ad-stat-card__value">156</p>
            <p class="ad-stat-card__label">Всего заявок за месяц</p>
        </article>
    </section>

    <section class="ad-quick" aria-label="Быстрый доступ">
        <h2>Быстрый доступ</h2>
        <div class="ad-quick__grid">
            <a class="ad-quick__item" href="{{ route('orders.index') }}">
                <strong>Все заявки</strong>
                <span>Просмотр и управление заявками</span>
            </a>
            <a class="ad-quick__item" href="#">
                <strong>Активные рейсы</strong>
                <span>Отслеживание доставок</span>
            </a>
            <a class="ad-quick__item" href="#">
                <strong>Водители</strong>
                <span>База данных водителей</span>
            </a>
        </div>
    </section>
@endsection
