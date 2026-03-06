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

    $participantsFilled = collect([
        $order['driver']['name'] ?? null,
        $order['sender']['name'] ?? null,
        $order['receiver']['name'] ?? null,
    ])->filter(static fn ($value) => filled($value))->count();
@endphp

<form class="order-edit" action="#" method="post">
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
                    <button type="button" class="ad-btn is-active">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-trips"></use></svg>
                        <span>Маршрут</span>
                    </button>
                    <button type="button" class="ad-btn">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                        <span>Участники</span>
                        <em>{{ $participantsFilled }}/4</em>
                    </button>
                    <button type="button" class="ad-btn">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-package"></use></svg>
                        <span>Груз</span>
                    </button>
                    <button type="button" class="ad-btn">
                        <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-rubl"></use></svg>
                        <span>Стоимость</span>
                    </button>
                </nav>

                <div class="order-edit-fields">
                    <section class="order-edit-group">
                        <h2>Откуда</h2>
                        <div class="order-edit-grid order-edit-grid--two">
                            <label>
                                <span>Город <em>*</em></span>
                                <div class="order-edit-input order-edit-input--icon">
                                    <svg aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                                    <input type="text" name="from_city" value="{{ $routeFromCity }}" placeholder="Москва">
                                </div>
                            </label>
                            <label>
                                <span>Адрес загрузки <em>*</em></span>
                                <div class="order-edit-input">
                                    <input type="text" name="from_address" value="{{ $routeFromAddress }}" placeholder="ул. Ленина, 12">
                                </div>
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
                                    <input type="text" name="to_city" value="{{ $routeToCity }}" placeholder="Санкт-Петербург">
                                </div>
                            </label>
                            <label>
                                <span>Адрес загрузки <em>*</em></span>
                                <div class="order-edit-input">
                                    <input type="text" name="to_address" value="{{ $routeToAddress }}" placeholder="пр. Невский, 45">
                                </div>
                            </label>
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
                    </section>
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
