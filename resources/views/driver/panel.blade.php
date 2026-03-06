<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Панель водителя — {{ config('app.name', 'Авто Доставка') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="driver-body">

    <div class="driver-portal">

        {{-- Header --}}
        <header class="driver-header">
            <div class="driver-header__inner">
                <div class="driver-header__logo">
                    <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true" class="driver-header__logo-icon"><use href="/icons/sprite.svg#icon-truck"></use></svg>
                    <span class="driver-header__logo-text">Авто Доставка</span>
                </div>
                <p class="driver-header__subtitle">Панель водителя</p>
            </div>
        </header>

        {{-- Main content --}}
        <main class="driver-main">

            {{-- Progress bar --}}
            <div class="driver-progress">
                <div class="driver-progress__labels">
                    <span class="driver-progress__label">Прогресс доставки</span>
                    <span class="driver-progress__value" id="driver-progress-pct">0%</span>
                </div>
                <div class="driver-progress__bar" id="driver-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                    <div class="driver-progress__fill" id="driver-progress-fill" style="width: 0%"></div>
                </div>
            </div>

            {{-- Transport info card --}}
            <div class="driver-card">
                <h2 class="driver-card__heading">Информация о перевозке</h2>

                <div class="driver-card__items">

                    {{-- Driver --}}
                    <div class="driver-card__item">
                        <div class="driver-card__icon-wrap is-blue">
                            <svg viewBox="0 0 20 20" focusable="false" aria-hidden="true" class="driver-card__icon"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                        </div>
                        <div class="driver-card__info">
                            <span class="driver-card__info-label">Водитель</span>
                            <span class="driver-card__info-value">{{ $driverName ?? 'Иванов Иван Петрович' }}</span>
                        </div>
                    </div>

                    {{-- Vehicle --}}
                    <div class="driver-card__item">
                        <div class="driver-card__icon-wrap is-purple">
                            <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true" class="driver-card__icon"><use href="/icons/sprite.svg#icon-truck"></use></svg>
                        </div>
                        <div class="driver-card__info">
                            <span class="driver-card__info-label">Транспорт</span>
                            <span class="driver-card__info-value">{{ $vehicleName ?? 'КАМАЗ 65115' }}</span>
                            <span class="driver-card__info-sub">{{ $vehiclePlate ?? 'А123ВС 777' }}</span>
                        </div>
                    </div>

                    {{-- Date & Time --}}
                    <div class="driver-card__row">
                        <div class="driver-card__item">
                            <div class="driver-card__icon-wrap is-green">
                                <svg viewBox="0 0 20 20" focusable="false" aria-hidden="true" class="driver-card__icon"><use href="/icons/sprite.svg#icon-doc-date"></use></svg>
                            </div>
                            <div class="driver-card__info">
                                <span class="driver-card__info-label">Дата</span>
                                <span class="driver-card__info-value">{{ $deliveryDate ?? date('d.m.Y') }}</span>
                            </div>
                        </div>
                        <div class="driver-card__item">
                            <div class="driver-card__icon-wrap is-yellow">
                                <svg viewBox="0 0 20 20" focusable="false" aria-hidden="true" class="driver-card__icon"><use href="/icons/sprite.svg#icon-time"></use></svg>
                            </div>
                            <div class="driver-card__info">
                                <span class="driver-card__info-label">Время</span>
                                <span class="driver-card__info-value">{{ $deliveryTime ?? date('H:i') }}</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Route card --}}
            <div class="driver-card">
                <h2 class="driver-card__heading driver-card__heading--with-icon">
                    <svg viewBox="0 0 20 20" focusable="false" aria-hidden="true" class="driver-card__heading-icon"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                    Маршрут
                </h2>

                <div class="driver-route">
                    <div class="driver-route__point">
                        <div class="driver-route__badge is-blue">А</div>
                        <div class="driver-route__info">
                            <span class="driver-route__label">Откуда</span>
                            <span class="driver-route__address">{{ $addressFrom ?? 'Москва, ул. Промышленная 15' }}</span>
                        </div>
                    </div>

                    <div class="driver-route__arrow">
                        <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><use href="/icons/sprite.svg#icon-driver-arrow"></use></svg>
                    </div>

                    <div class="driver-route__point">
                        <div class="driver-route__badge is-green">Б</div>
                        <div class="driver-route__info">
                            <span class="driver-route__label">Куда</span>
                            <span class="driver-route__address">{{ $addressTo ?? 'Санкт-Петербург, пр. Обуховской Обороны 120' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action button (state machine) --}}
            <button type="button" id="driver-action-btn" class="ad-btn">
                <svg id="driver-btn-icon" viewBox="0 0 24 24" focusable="false" aria-hidden="true"><use id="driver-btn-icon-use" href="/icons/sprite.svg#icon-truck"></use></svg>
                <span id="driver-btn-title">Начать перевозку</span>
                <span id="driver-btn-sub">Нажмите для начала доставки</span>
            </button>

            {{-- Order number --}}
            <div class="driver-order-num">
                <span class="driver-order-num__label">Номер заявки</span>
                <span class="driver-order-num__value">{{ $orderNumber ?? 'АД-' . date('Ymd') . '-001' }}</span>
            </div>

            {{-- Support --}}
            <div class="driver-support">
                <p class="driver-support__text">Если возникли вопросы, звоните диспетчеру:</p>
                <a href="tel:+78000000000" class="driver-support__phone">
                    <svg viewBox="0 0 20 20" focusable="false" aria-hidden="true" class="driver-support__phone-icon"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg>
                    <span>+7 (800) 000-00-00</span>
                </a>
            </div>

        </main>
    </div>

    <script>
        (function () {
            var ICON_START   = '/icons/sprite.svg#icon-truck';
            var ICON_LOADING = '/icons/sprite.svg#icon-truck';

            // State machine
            // Each state: what the button shows AFTER entering this state
            var STATES = [
                // 1 — initial
                {
                    color:    '',
                    icon:     ICON_START,
                    spin:     false,
                    title:    'Начать перевозку',
                    sub:      'Нажмите для начала доставки',
                    progress: 0,
                    disabled: false
                },
                // 2 — en route to pickup (после нажатия "Начать перевозку")
                {
                    color:    'is-en-route',
                    icon:     ICON_START,
                    spin:     false,
                    title:    'Начать загрузку',
                    sub:      'В пути к месту загрузки',
                    progress: 20,
                    disabled: false
                },
                // 3 — loading cargo (после нажатия "Начать загрузку")
                {
                    color:    'is-loading',
                    icon:     ICON_LOADING,
                    spin:     true,
                    title:    'Начать движение',
                    sub:      'Идёт погрузка товара...',
                    progress: 40,
                    disabled: false
                },
                // 4 — in transit (после нажатия "Начать движение")
                {
                    color:    'is-transit',
                    icon:     ICON_START,
                    spin:     false,
                    title:    'Начать разгрузку',
                    sub:      'В пути к месту разгрузки',
                    progress: 60,
                    disabled: false
                },
                // 5 — unloading (после нажатия "Начать разгрузку")
                {
                    color:    'is-unloading',
                    icon:     ICON_START,
                    spin:     false,
                    title:    'Завершить перевозку',
                    sub:      'Идёт разгрузка товара...',
                    progress: 80,
                    disabled: false
                },
                // 6 — done (после нажатия "Завершить перевозку")
                {
                    color:    'is-done',
                    icon:     ICON_START,
                    spin:     false,
                    title:    'Перевозка завершена',
                    sub:      'Доставка выполнена успешно ✓',
                    progress: 100,
                    disabled: true
                }
            ];

            var COLOR_CLASSES = ['is-en-route', 'is-loading', 'is-transit', 'is-unloading', 'is-done'];

            var currentState = 0;

            var btn          = document.getElementById('driver-action-btn');
            var btnIcon      = document.getElementById('driver-btn-icon');
            var btnIconUse   = document.getElementById('driver-btn-icon-use');
            var btnTitle     = document.getElementById('driver-btn-title');
            var btnSub       = document.getElementById('driver-btn-sub');
            var progressFill = document.getElementById('driver-progress-fill');
            var progressBar  = document.getElementById('driver-progress-bar');
            var progressPct  = document.getElementById('driver-progress-pct');

            function applyState(index) {
                var s = STATES[index];

                // Update color class
                COLOR_CLASSES.forEach(function (c) { btn.classList.remove(c); });
                if (s.color) btn.classList.add(s.color);

                // Update icon + spin
                if (btnIconUse) {
                    btnIconUse.setAttribute('href', s.icon);
                }
                // Update text
                btnTitle.textContent = s.title;
                btnSub.textContent   = s.sub;

                // Update progress
                progressFill.style.width = s.progress + '%';
                progressBar.setAttribute('aria-valuenow', s.progress);
                progressPct.textContent  = s.progress + '%';

                // Update disabled state
                btn.disabled = s.disabled;
            }

            btn.addEventListener('click', function () {
                if (currentState < STATES.length - 1) {
                    currentState++;
                    applyState(currentState);
                }
            });
        })();
    </script>

</body>
</html>
