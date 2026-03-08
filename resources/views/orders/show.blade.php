@extends('layouts.app')

@php
    $activeMenu = 'orders';
    $orderId = $order['id'] ?? null;
    $routeMapPoints = is_array($routeMapPoints ?? null) ? $routeMapPoints : [];
    $canRenderRouteMap = count($routeMapPoints) >= 2;
    $routeMapFallbackEmbedUrl = null;

    if ($canRenderRouteMap) {
        $lngValues = array_map(static fn (array $point): float => (float) ($point['lng'] ?? 0), $routeMapPoints);
        $latValues = array_map(static fn (array $point): float => (float) ($point['lat'] ?? 0), $routeMapPoints);

        $minLng = min($lngValues);
        $maxLng = max($lngValues);
        $minLat = min($latValues);
        $maxLat = max($latValues);

        $lngSpan = max($maxLng - $minLng, 0.02);
        $latSpan = max($maxLat - $minLat, 0.02);
        $lngPad = $lngSpan * 0.15;
        $latPad = $latSpan * 0.15;

        $minLng -= $lngPad;
        $maxLng += $lngPad;
        $minLat -= $latPad;
        $maxLat += $latPad;

        $bbox = implode(',', [$minLng, $minLat, $maxLng, $maxLat]);
        $centerLat = ($minLat + $maxLat) / 2;
        $centerLng = ($minLng + $maxLng) / 2;

        $routeMapFallbackEmbedUrl = 'https://www.openstreetmap.org/export/embed.html?bbox='
            . rawurlencode($bbox)
            . '&layer=mapnik&marker='
            . rawurlencode($centerLat . ',' . $centerLng);
    }
@endphp

@section('title', 'Заявка ' . $order['number'] . ' — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="order-show">
        <div class="order-show__top">
            <a class="orders-back" href="{{ route('orders.index') }}">← Вернуться к списку</a>
            <a class="ad-btn" href="{{ is_numeric($orderId) ? route('orders.edit', (int) $orderId) : route('orders.index') }}">
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

                            @if (!empty($order['route']['intermediate']))
                                <div class="route-box__via">
                                    <p class="route-box__label">Промежуточные точки</p>
                                    <ul class="route-box__via-list">
                                        @foreach ($order['route']['intermediate'] as $stop)
                                            <li>
                                                <strong>{{ ($stop['type'] ?? 'unloading') === 'loading' ? 'Загрузка' : 'Выгрузка' }}:</strong>
                                                {{ $stop['city'] ?? '—' }}
                                                @if (!empty($stop['address']))
                                                    — {{ $stop['address'] }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>

                        @if ($canRenderRouteMap)
                            <div class="route-map route-map--dynamic" data-order-route-map>
                                <div
                                    class="route-map__canvas"
                                    data-order-route-map-canvas
                                    data-map-points='@json($routeMapPoints)'
                                >
                                    @if ($routeMapFallbackEmbedUrl)
                                        <iframe
                                            title="Резервная карта маршрута"
                                            loading="lazy"
                                            referrerpolicy="no-referrer-when-downgrade"
                                            src="{{ $routeMapFallbackEmbedUrl }}"
                                            style="width:100%;height:100%;min-height:14rem;border:0;display:block;"
                                        ></iframe>
                                    @endif
                                </div>
                            </div>

                            <p class="route-map__status" data-order-route-map-status>
                                Определяю режим карты…
                            </p>

                            <p class="route-map__fallback" data-order-route-map-error hidden>
                                Не удалось загрузить интерактивную карту.
                            </p>
                        @else
                            <p class="route-map__fallback">Интерактивная карта недоступна: проверьте API-ключ и координаты маршрута.</p>
                        @endif
                    </div>
                </section>

                <section class="order-card order-card--cost" aria-label="Стоимость перевозки для заказчика">
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
                            <h2 class="order-cost-card__title">Стоимость перевозки для заказчика</h2>
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

                <section class="order-card order-card--cost" aria-label="Стоимость перевозки для Автодоставка">
                    @php
                        $autodostCostWithVat = (int) ($order['autodostavka_cost'] ?? $order['cost'] ?? 0);
                        $autodostVatRate = 20;
                        $autodostCostWithoutVat = (int) round($autodostCostWithVat / (1 + ($autodostVatRate / 100)));
                        $autodostVatAmount = max($autodostCostWithVat - $autodostCostWithoutVat, 0);
                    @endphp

                    <div class="order-cost-card__body">
                        <div class="order-cost-card__title-row">
                            <span class="order-cost-card__icon" aria-hidden="true">
                                <svg><use href="/icons/sprite.svg#icon-rubl"></use></svg>
                            </span>
                            <h2 class="order-cost-card__title">Стоимость перевозки для Автодоставка</h2>
                        </div>

                        <div class="order-cost">
                            <div class="order-cost__item">
                                <p class="order-cost__label">Без НДС</p>
                                <p class="order-cost__value">{{ number_format($autodostCostWithoutVat, 0, ',', ' ') }} ₽</p>
                            </div>
                            <div class="order-cost__item">
                                <p class="order-cost__label">Ставка НДС</p>
                                <p class="order-cost__value">{{ $autodostVatRate }}%</p>
                            </div>
                            <div class="order-cost__item">
                                <p class="order-cost__label">Сумма НДС</p>
                                <p class="order-cost__value order-cost__value--muted">{{ number_format($autodostVatAmount, 0, ',', ' ') }} ₽</p>
                            </div>
                            <div class="order-cost__item">
                                <p class="order-cost__label">Итого с НДС</p>
                                <p class="order-cost__value order-cost__value--total">{{ number_format($autodostCostWithVat, 0, ',', ' ') }} ₽</p>
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

@if (!empty($yandexJsApiKey))
    @push('scripts')
        <script src="https://api-maps.yandex.ru/v3/?apikey={{ $yandexJsApiKey }}&lang=ru_RU" type="text/javascript" data-yandex-map-v3="1"></script>
    @endpush
@endif
