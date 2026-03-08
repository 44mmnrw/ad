@php
    $isEdit = !empty($order);
    $orderNumber = $order['number'] ?? 'АД-20240315-001';
    $routeFromCity = $order['route']['from']['city'] ?? '';
    $routeFromAddress = $order['route']['from']['address'] ?? '';
    $routeToCity = $order['route']['to']['city'] ?? '';
    $routeToAddress = $order['route']['to']['address'] ?? '';
    $distanceRaw = $order['distance'] ?? '';
    $distanceValue = trim((string) preg_replace('/\D+/', '', (string) $distanceRaw));
    $dateValue = $order['created_at'] ?? now()->format('d.m.Y');

    $carrierName = $order['carrier']['name'] ?? null;
    $driverName = $order['driver']['name'] ?? null;
    $senderName = $order['sender']['name'] ?? null;
    $receiverName = $order['receiver']['name'] ?? null;
    $fromLat = $order['route']['from']['lat'] ?? null;
    $fromLng = $order['route']['from']['lng'] ?? null;
    $toLat = $order['route']['to']['lat'] ?? null;
    $toLng = $order['route']['to']['lng'] ?? null;
    $counterpartyOptions = is_iterable($counterparties ?? null) ? $counterparties : [];
    $intermediateStops = collect($order['route']['intermediate'] ?? [])
        ->filter(static fn ($stop) => is_array($stop))
        ->map(static fn (array $stop) => [
            'type' => in_array(($stop['type'] ?? 'unloading'), ['loading', 'unloading'], true) ? (string) $stop['type'] : 'unloading',
            'counterparty_id' => isset($stop['counterparty_id']) ? (int) $stop['counterparty_id'] : null,
            'city' => (string) ($stop['city'] ?? ''),
            'address' => (string) ($stop['address'] ?? ''),
            'lat' => is_numeric($stop['lat'] ?? null) ? (float) $stop['lat'] : null,
            'lng' => is_numeric($stop['lng'] ?? null) ? (float) $stop['lng'] : null,
        ])
        ->values()
        ->all();

    $participantsFilled = collect([
        $driverName,
        $senderName,
        $receiverName,
    ])->filter(static fn ($value) => filled($value))->count();
@endphp

<form
    class="order-edit"
    action="{{ $isEdit ? route('orders.update', $order['id'] ?? 1) : route('orders.store') }}"
    method="post"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <div class="order-edit__layout">
        <div class="order-edit__main">
            <section class="order-edit-card order-edit-head">
                <div class="order-edit-head__icon" aria-hidden="true">
                    <svg><use href="/icons/sprite.svg#icon-orders"></use></svg>
                </div>
                <div class="order-edit-head__content">
                    <h1 class="ad-h1">{{ $isEdit ? 'Редактирование заявки ' . $orderNumber : 'Новая заявка' }}</h1>
                    <p>{{ ($routeFromCity ?: 'Город отправления') . ' → ' . ($routeToCity ?: 'Город назначения') }}</p>
                </div>
                <span class="order-edit-head__badge">#{{ $orderNumber }}</span>
            </section>

            <section class="order-edit-card order-edit-form-card">
                <nav class="order-edit-tabs" aria-label="Разделы заявки">
                    <button type="button" class="ad-btn" data-order-tab="route" aria-controls="order-tab-panel-route" aria-selected="false">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-trips"></use></svg>
                        <span>Маршрут</span>
                    </button>
                    <button type="button" class="ad-btn is-active" data-order-tab="participants" aria-controls="order-tab-panel-participants" aria-selected="true">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                        <span>Участники</span>
                        <em>{{ $participantsFilled }}/4</em>
                    </button>
                    <button type="button" class="ad-btn" data-order-tab="cargo" aria-controls="order-tab-panel-cargo" aria-selected="false">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-package"></use></svg>
                        <span>Груз</span>
                    </button>
                    <button type="button" class="ad-btn" data-order-tab="cost" aria-controls="order-tab-panel-cost" aria-selected="false">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-rubl"></use></svg>
                        <span>Стоимость</span>
                    </button>
                </nav>

                <div class="order-edit-tab-panels">
                    <div id="order-tab-panel-route" class="order-edit-tab-panel" data-order-tab-panel="route" hidden>
                        <div class="order-edit-fields">
                            <section class="order-edit-group">
                                <h2>Откуда</h2>
                                <div class="order-edit-grid order-edit-grid--two">
                                    <label>
                                        <span>Город <em>*</em></span>
                                        <div class="order-edit-input order-edit-input--icon">
                                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                                            <input type="text" name="from_city" value="{{ $routeFromCity }}" placeholder="Москва" data-route-input="from_city" data-route-city-input autocomplete="off" list="order-city-suggest-from">
                                        </div>
                                        <datalist id="order-city-suggest-from"></datalist>
                                    </label>
                                    <label>
                                        <span>Адрес загрузки <em>*</em></span>
                                        <div class="order-edit-input">
                                            <input type="text" name="from_address" value="{{ $routeFromAddress }}" placeholder="ул. Ленина, 12" data-route-input="from_address" data-route-address-input autocomplete="off" list="order-address-suggest-from">
                                        </div>
                                        <datalist id="order-address-suggest-from"></datalist>
                                        <p class="order-route-point" data-route-point="from">{{ is_numeric($fromLat) && is_numeric($fromLng) ? 'Точка: '.number_format((float) $fromLat, 6, '.', '').', '.number_format((float) $fromLng, 6, '.', '') : 'Точка не определена' }}</p>
                                    </label>
                                </div>
                            </section>

                            <section class="order-edit-group">
                                <h2>Куда</h2>
                                <div class="order-edit-grid order-edit-grid--two">
                                    <label>
                                        <span>Город <em>*</em></span>
                                        <div class="order-edit-input order-edit-input--icon">
                                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                                            <input type="text" name="to_city" value="{{ $routeToCity }}" placeholder="Санкт-Петербург" data-route-input="to_city" data-route-city-input autocomplete="off" list="order-city-suggest-to">
                                        </div>
                                        <datalist id="order-city-suggest-to"></datalist>
                                    </label>
                                    <label>
                                        <span>Адрес загрузки <em>*</em></span>
                                        <div class="order-edit-input">
                                            <input type="text" name="to_address" value="{{ $routeToAddress }}" placeholder="пр. Невский, 45" data-route-input="to_address" data-route-address-input autocomplete="off" list="order-address-suggest-to">
                                        </div>
                                        <datalist id="order-address-suggest-to"></datalist>
                                        <p class="order-route-point" data-route-point="to">{{ is_numeric($toLat) && is_numeric($toLng) ? 'Точка: '.number_format((float) $toLat, 6, '.', '').', '.number_format((float) $toLng, 6, '.', '') : 'Точка не определена' }}</p>
                                    </label>
                                </div>
                            </section>

                            <section class="order-edit-group">
                                <h2>Промежуточные пункты</h2>

                                <div class="order-route-stops" data-route-intermediate-list>
                                    @forelse ($intermediateStops as $stop)
                                        <div class="order-route-stop">
                                            <div class="order-edit-grid order-edit-grid--two">
                                                <label>
                                                    <span>Тип пункта</span>
                                                    <div class="order-edit-input">
                                                        <select name="intermediate_types[]" data-custom-select>
                                                            <option value="loading" @selected(($stop['type'] ?? 'unloading') === 'loading')>Загрузка</option>
                                                            <option value="unloading" @selected(($stop['type'] ?? 'unloading') === 'unloading')>Выгрузка</option>
                                                        </select>
                                                    </div>
                                                </label>
                                                <label>
                                                    <span>Контрагент (опционально)</span>
                                                    <div class="order-edit-input">
                                                        <select name="intermediate_counterparty_ids[]" data-custom-select>
                                                            <option value="">По умолчанию: Заказчик</option>
                                                            @foreach ($counterpartyOptions as $cp)
                                                                <option value="{{ $cp['id'] }}" @selected((string) ($stop['counterparty_id'] ?? '') === (string) $cp['id'])>
                                                                    {{ $cp['label'] }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </label>
                                                <label>
                                                    <span>Город</span>
                                                    <div class="order-edit-input order-edit-input--icon">
                                                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                                                        <input
                                                            type="text"
                                                            name="intermediate_cities[]"
                                                            value="{{ $stop['city'] }}"
                                                            placeholder="Тверь"
                                                            autocomplete="off"
                                                            list="order-city-suggest-waypoints"
                                                            data-route-city-input
                                                        >
                                                    </div>
                                                </label>
                                                <label>
                                                    <span>Адрес</span>
                                                    <div class="order-edit-input">
                                                        <input
                                                            type="text"
                                                            name="intermediate_addresses[]"
                                                            value="{{ $stop['address'] }}"
                                                            placeholder="ул. Центральная, 1"
                                                            autocomplete="off"
                                                            list="order-address-suggest-waypoints"
                                                            data-route-address-input
                                                        >
                                                    </div>
                                                </label>
                                                <input type="hidden" name="intermediate_lats[]" value="{{ is_numeric($stop['lat'] ?? null) ? number_format((float) $stop['lat'], 6, '.', '') : '' }}">
                                                <input type="hidden" name="intermediate_lngs[]" value="{{ is_numeric($stop['lng'] ?? null) ? number_format((float) $stop['lng'], 6, '.', '') : '' }}">
                                            </div>
                                            <button type="button" class="ad-btn order-route-stop__remove" data-route-intermediate-remove>
                                                Удалить пункт
                                            </button>
                                        </div>
                                    @empty
                                        <p class="order-route-stops__empty" data-route-intermediate-empty>
                                            Промежуточные пункты не добавлены.
                                        </p>
                                    @endforelse
                                </div>

                                <datalist id="order-city-suggest-waypoints"></datalist>
                                <datalist id="order-address-suggest-waypoints"></datalist>

                                <template data-route-intermediate-template>
                                    <div class="order-route-stop">
                                        <div class="order-edit-grid order-edit-grid--two">
                                            <label>
                                                <span>Тип пункта</span>
                                                <div class="order-edit-input">
                                                    <select name="intermediate_types[]" data-custom-select>
                                                        <option value="loading">Загрузка</option>
                                                        <option value="unloading" selected>Выгрузка</option>
                                                    </select>
                                                </div>
                                            </label>
                                            <label>
                                                <span>Контрагент (опционально)</span>
                                                <div class="order-edit-input">
                                                    <select name="intermediate_counterparty_ids[]" data-custom-select>
                                                        <option value="" selected>По умолчанию: Заказчик</option>
                                                        @foreach ($counterpartyOptions as $cp)
                                                            <option value="{{ $cp['id'] }}">{{ $cp['label'] }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </label>
                                            <label>
                                                <span>Город</span>
                                                <div class="order-edit-input order-edit-input--icon">
                                                    <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                                                    <input
                                                        type="text"
                                                        name="intermediate_cities[]"
                                                        placeholder="Тверь"
                                                        autocomplete="off"
                                                        list="order-city-suggest-waypoints"
                                                        data-route-city-input
                                                    >
                                                </div>
                                            </label>
                                            <label>
                                                <span>Адрес</span>
                                                <div class="order-edit-input">
                                                    <input
                                                        type="text"
                                                        name="intermediate_addresses[]"
                                                        placeholder="ул. Центральная, 1"
                                                        autocomplete="off"
                                                        list="order-address-suggest-waypoints"
                                                        data-route-address-input
                                                    >
                                                </div>
                                            </label>
                                            <input type="hidden" name="intermediate_lats[]" value="">
                                            <input type="hidden" name="intermediate_lngs[]" value="">
                                        </div>
                                        <button type="button" class="ad-btn order-route-stop__remove" data-route-intermediate-remove>
                                            Удалить пункт
                                        </button>
                                    </div>
                                </template>

                                <div class="order-route-stops__actions">
                                    <button type="button" class="ad-btn" data-route-intermediate-add>
                                        Добавить промежуточный пункт
                                    </button>
                                </div>
                            </section>

                            <section class="order-edit-group">
                                <h2>Параметры</h2>
                                <div class="order-edit-grid order-edit-grid--two">
                                    <label>
                                        <span>Расстояние (км) <em>*</em></span>
                                        <div class="order-edit-input order-edit-input--icon">
                                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-driver-arrow"></use></svg>
                                            <input type="text" name="distance" value="{{ $distanceValue }}" placeholder="480">
                                        </div>
                                    </label>
                                    <label>
                                        <span>Дата <em>*</em></span>
                                        <div class="order-edit-input order-edit-input--icon">
                                            <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-date"></use></svg>
                                            <input type="text" name="created_at" value="{{ $dateValue }}" placeholder="15.03.2026">
                                        </div>
                                    </label>
                                </div>

                                <div class="order-edit-route-tools">
                                    <button type="button" class="ad-btn" data-route-geocode-button>
                                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-driver-arrow"></use></svg>
                                        <span>Определить точки маршрута</span>
                                    </button>
                                    <p class="order-edit-route-status" data-route-status role="status" aria-live="polite"></p>
                                </div>

                                <input type="hidden" name="from_lat" value="{{ is_numeric($fromLat) ? number_format((float) $fromLat, 6, '.', '') : '' }}" data-route-coord="from_lat">
                                <input type="hidden" name="from_lng" value="{{ is_numeric($fromLng) ? number_format((float) $fromLng, 6, '.', '') : '' }}" data-route-coord="from_lng">
                                <input type="hidden" name="to_lat" value="{{ is_numeric($toLat) ? number_format((float) $toLat, 6, '.', '') : '' }}" data-route-coord="to_lat">
                                <input type="hidden" name="to_lng" value="{{ is_numeric($toLng) ? number_format((float) $toLng, 6, '.', '') : '' }}" data-route-coord="to_lng">
                            </section>
                        </div>
                    </div>

                    <div id="order-tab-panel-participants" class="order-edit-tab-panel" data-order-tab-panel="participants">
                        <div class="order-edit-participants-tab">
                            <h2 class="order-edit-participants-tab__title">Стороны перевозки</h2>

                            <button type="button" class="order-participant-card order-participant-card--carrier" aria-label="Грузоперевозчик">
                                <span class="order-participant-card__icon order-participant-card__icon--carrier" aria-hidden="true">
                                    <svg><use href="/icons/sprite.svg#icon-doc-briefcase"></use></svg>
                                </span>
                                <span class="order-participant-card__content">
                                    <span class="order-participant-card__name">Грузоперевозчик</span>
                                    <span class="order-participant-card__meta">{{ $carrierName ?: 'Транспортная компания, выполняющая перевозку' }}</span>
                                </span>
                                <span class="order-participant-card__status order-participant-card__status--empty">Не указан</span>
                                <span class="order-participant-card__arrow" aria-hidden="true">
                                    <svg><use href="/icons/sprite.svg#icon-doc-left-arrow"></use></svg>
                                </span>
                            </button>

                            <button type="button" class="order-participant-card order-participant-card--driver" aria-label="Водитель">
                                <span class="order-participant-card__icon order-participant-card__icon--driver" aria-hidden="true">
                                    <svg><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                                </span>
                                <span class="order-participant-card__content">
                                    <span class="order-participant-card__name">Водитель <span class="order-participant-card__check order-participant-card__check--green">✓</span></span>
                                    <span class="order-participant-card__meta">{{ $driverName ?: 'Не указан' }}</span>
                                </span>
                                <span class="order-participant-card__status order-participant-card__status--green">Заполнен</span>
                                <span class="order-participant-card__arrow" aria-hidden="true">
                                    <svg><use href="/icons/sprite.svg#icon-doc-left-arrow"></use></svg>
                                </span>
                            </button>

                            <button type="button" class="order-participant-card order-participant-card--sender" aria-label="Грузоотправитель">
                                <span class="order-participant-card__icon order-participant-card__icon--sender" aria-hidden="true">
                                    <svg><use href="/icons/sprite.svg#icon-doc-package"></use></svg>
                                </span>
                                <span class="order-participant-card__content">
                                    <span class="order-participant-card__name">Грузоотправитель <span class="order-participant-card__check order-participant-card__check--blue">✓</span></span>
                                    <span class="order-participant-card__meta">{{ $senderName ?: 'Не указан' }}</span>
                                </span>
                                <span class="order-participant-card__status order-participant-card__status--blue">Заполнен</span>
                                <span class="order-participant-card__arrow" aria-hidden="true">
                                    <svg><use href="/icons/sprite.svg#icon-doc-left-arrow"></use></svg>
                                </span>
                            </button>

                            <button type="button" class="order-participant-card order-participant-card--receiver" aria-label="Грузополучатель">
                                <span class="order-participant-card__icon order-participant-card__icon--receiver" aria-hidden="true">
                                    <svg><use href="/icons/sprite.svg#icon-doc-packagecheck"></use></svg>
                                </span>
                                <span class="order-participant-card__content">
                                    <span class="order-participant-card__name">Грузополучатель <span class="order-participant-card__check order-participant-card__check--green">✓</span></span>
                                    <span class="order-participant-card__meta">{{ $receiverName ?: 'Не указан' }}</span>
                                </span>
                                <span class="order-participant-card__status order-participant-card__status--emerald">Заполнен</span>
                                <span class="order-participant-card__arrow" aria-hidden="true">
                                    <svg><use href="/icons/sprite.svg#icon-doc-left-arrow"></use></svg>
                                </span>
                            </button>
                        </div>
                    </div>

                    <div id="order-tab-panel-cargo" class="order-edit-tab-panel" data-order-tab-panel="cargo" hidden>
                        <div class="order-edit-fields">
                            <section class="order-edit-group">
                                <h2>Груз</h2>
                                <div class="order-edit-tab-note">Раздел груза будет заполнен на следующем шаге.</div>
                            </section>
                        </div>
                    </div>

                    <div id="order-tab-panel-cost" class="order-edit-tab-panel" data-order-tab-panel="cost" hidden>
                        <div class="order-edit-fields">
                            <section class="order-edit-group">
                                <h2>Стоимость</h2>
                                <div class="order-edit-tab-note">Раздел стоимости будет заполнен на следующем шаге.</div>
                            </section>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="order-edit__side" aria-label="Панель заявки">
            <section class="order-edit-card order-edit-side-card">
                <h3>Действия</h3>
                <div class="order-edit-actions">
                    <button type="submit" class="ad-btn">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-save"></use></svg>
                        <span>Сохранить изменения</span>
                    </button>
                    <a class="ad-btn" href="{{ $backRoute }}">Отмена</a>
                    <button type="button" class="ad-btn">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-trash"></use></svg>
                        <span>Удалить заявку</span>
                    </button>
                </div>
            </section>

            <section class="order-edit-card order-edit-side-card">
                <h3>Участники</h3>
                <ul class="order-edit-participants">
                    <li>
                        <span class="order-edit-participants__name"><svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-briefcase"></use></svg>Грузоперевозчик</span>
                        <span class="order-edit-participants__state">—</span>
                    </li>
                    <li>
                        <span class="order-edit-participants__name"><svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>Водитель</span>
                        <span class="order-edit-participants__state is-ok">✓</span>
                    </li>
                    <li>
                        <span class="order-edit-participants__name"><svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-package"></use></svg>Грузоотправитель</span>
                        <span class="order-edit-participants__state is-ok">✓</span>
                    </li>
                    <li>
                        <span class="order-edit-participants__name"><svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-packagecheck"></use></svg>Грузополучатель</span>
                        <span class="order-edit-participants__state is-ok">✓</span>
                    </li>
                </ul>
            </section>

            <section class="order-edit-card order-edit-side-card">
                <h3>Сводка</h3>
                <ul class="order-edit-summary">
                    <li><span>Маршрут</span><em class="is-green">Заполнен</em></li>
                    <li><span>Участники</span><em class="is-blue">{{ $participantsFilled }} / 4</em></li>
                    <li><span>Груз</span><em class="is-gray">Не указан</em></li>
                </ul>
            </section>

            <section class="order-edit-card order-edit-side-card">
                <h3>Информация</h3>
                <div class="order-edit-info">
                    <div>
                        <span class="order-edit-info__label">Номер заявки</span>
                        <span class="order-edit-info__value">{{ $orderNumber }}</span>
                    </div>
                    <div>
                        <span class="order-edit-info__label">Дата создания</span>
                        <span class="order-edit-info__value">{{ $order['created_at'] ?? '15.03.2024' }}</span>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</form>
