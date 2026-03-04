<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — {{ config('app.name', 'Авто Доставка') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="ad-auth-body">
    <main class="ad-auth-layout">
        <section class="ad-auth-hero" aria-label="О платформе">
            <div class="ad-auth-hero__glow ad-auth-hero__glow--top"></div>
            <div class="ad-auth-hero__glow ad-auth-hero__glow--bottom"></div>

            <header class="ad-auth-hero__brand">
                <div class="ad-auth-hero__logo" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><use href="/icons/sprite.svg#icon-auth-truck"></use></svg>
                </div>
                <div>
                    <p class="ad-auth-hero__brand-title">Авто Доставка</p>
                    <p class="ad-auth-hero__brand-subtitle">Корпоративный портал</p>
                </div>
            </header>

            <div class="ad-auth-hero__content">
                <span class="ad-auth-hero__badge"><span></span>Версия 1.0 — Март 2026</span>
                <h2 class="ad-auth-hero__title">Система управления<br><em>грузоперевозками</em></h2>
                <p class="ad-auth-hero__text">Полный контроль над заявками, рейсами и логистикой в едином корпоративном кабинете.</p>

                <ul class="ad-auth-hero__list">
                    <li><span class="ad-auth-hero__list-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-dashboard"></use></svg></span><span>Дашборд с аналитикой в реальном времени</span></li>
                    <li><span class="ad-auth-hero__list-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-trips"></use></svg></span><span>Управление рейсами и маршрутами</span></li>
                    <li><span class="ad-auth-hero__list-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-drivers"></use></svg></span><span>Контроль транспорта и водителей</span></li>
                    <li><span class="ad-auth-hero__list-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-settings"></use></svg></span><span>Защищённый корпоративный доступ</span></li>
                </ul>
            </div>

            <div class="ad-auth-hero__stats">
                <article><strong>1 240</strong><span>Заявок в месяц</span></article>
                <article><strong>98%</strong><span>Доставок вовремя</span></article>
                <article><strong>47</strong><span>Водителей в сети</span></article>
            </div>
        </section>

        <section class="ad-auth-panel" aria-label="Форма входа">
            <div class="ad-auth-panel__inner">
                <div class="ad-auth-panel__head">
                    <h1 id="login-title" class="ad-auth-title">Добро пожаловать</h1>
                    <p class="ad-auth-subtitle">Введите данные для входа в систему</p>
                </div>

                <section class="ad-auth-card" aria-labelledby="login-title">
                    @if ($errors->any())
                        <div class="ad-auth-alert" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form class="ad-auth-form" method="POST" action="{{ route('login.store') }}">
                        @csrf

                        <div class="ad-auth-field">
                            <label for="email">Логин / Email</label>
                            <div class="ad-auth-input-wrap">
                                <span class="ad-auth-input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-auth-mail"></use></svg>
                                </span>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    placeholder="logist@avtodostavka.ru"
                                    autocomplete="username"
                                    required
                                    autofocus
                                >
                            </div>
                        </div>

                        <div class="ad-auth-field">
                            <div class="ad-auth-field__row">
                                <label for="password">Пароль</label>
                                <a href="#" class="ad-auth-link" aria-disabled="true">Забыли пароль?</a>
                            </div>
                            <div class="ad-auth-input-wrap">
                                <span class="ad-auth-input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-auth-lock"></use></svg>
                                </span>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    placeholder="••••••••"
                                    autocomplete="current-password"
                                    required
                                >
                            </div>
                        </div>

                        <label class="ad-auth-check" for="remember">
                            <input id="remember" name="remember" type="checkbox" value="1" {{ old('remember') ? 'checked' : '' }}>
                            <span>Запомнить меня на этом устройстве</span>
                        </label>

                        <button class="ad-auth-submit" type="submit">
                            <span>Войти в систему</span>
                            <span class="ad-auth-submit__icon" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-auth-arrow-right"></use></svg></span>
                        </button>
                    </form>
                </section>

                <p class="ad-auth-panel__footer">© 2026 Авто Доставка. Корпоративная система управления грузоперевозками.</p>
            </div>
        </section>
    </main>
</body>
</html>
