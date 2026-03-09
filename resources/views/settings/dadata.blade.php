@extends('layouts.app')

@section('title', 'Настройки DaData — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="settings-page">
        <header class="settings-page__head">
            <h1 class="ad-h1">Интеграция DaData</h1>
            <p>API DaData: автозаполнение по ИНН/ОГРН и поиск организаций/ИП по названию.</p>
            <div class="settings-page__actions">
                <a class="ad-btn" href="{{ route('settings.icons.preview') }}">
                    Открыть превью иконок спрайта
                </a>
            </div>
        </header>

        @if (session('status'))
            <div class="cp-alert cp-alert--success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="cp-create__errors" role="alert">
                <p>Не удалось сохранить настройки:</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('dadata_test_result'))
            <div class="settings-page__hint settings-page__hint--stack" role="status">
                <strong>Результат теста:</strong>
                <span>{{ session('dadata_test_result') }}</span>
                @if (session('dadata_test_mode'))
                    <span>Режим: <strong>{{ session('dadata_test_mode') }}</strong></span>
                @endif
                <span>Подсказка: в JSON ищите <code>suggestions[].data.management</code> — там поля <code>name</code> и <code>post</code>.</span>
                @if (session('dadata_test_payload'))
                    <pre class="settings-page__json">{{ session('dadata_test_payload') }}</pre>
                @endif
            </div>
        @endif

        <section class="settings-card" aria-label="Параметры API DaData">
            <form action="{{ route('settings.dadata.update') }}" method="post" class="settings-form">
                @csrf
                @method('PUT')

                <label class="settings-form__field">
                    <span>API key <em>*</em></span>
                    <input type="text" name="api_key" value="{{ old('api_key', $apiKey) }}" placeholder="Введите API-ключ DaData" required>
                </label>

                <label class="settings-form__field">
                    <span>Secret key</span>
                    <input type="text" name="secret_key" value="{{ old('secret_key', $secretKey) }}" placeholder="Введите secret-ключ DaData (опционально)">
                </label>

                <label class="settings-form__field settings-form__field--sm">
                    <span>Timeout, сек <em>*</em></span>
                    <input type="number" min="1" max="60" name="timeout" value="{{ old('timeout', $timeout) }}" required>
                </label>

                <div class="settings-form__actions">
                    <button type="submit" class="ad-btn">Сохранить</button>
                </div>
            </form>
        </section>

        <section class="settings-card" aria-label="Параметры API Яндекс Карт">
            <form action="{{ route('settings.yandex_maps.update') }}" method="post" class="settings-form settings-form--stack">
                @csrf
                @method('PUT')

                <label class="settings-form__field">
                    <span>API key Яндекс Карт</span>
                    <input type="text" name="yandex_static_api_key" value="{{ old('yandex_static_api_key', $yandexStaticApiKey) }}" placeholder="Введите API-ключ Static Maps API (опционально)">
                </label>

                <label class="settings-form__field">
                    <span>JavaScript API v3</span>
                    <input type="text" name="yandex_js_api_key" value="{{ old('yandex_js_api_key', $yandexJsApiKey) }}" placeholder="Введите отдельный API-ключ для Yandex Maps JS API v3">
                </label>

                <label class="settings-form__field">
                    <span>HTTP Геокодер</span>
                    <input type="text" name="yandex_http_geocoder_api_key" value="{{ old('yandex_http_geocoder_api_key', $yandexHttpGeocoderApiKey) }}" placeholder="Введите API-ключ для HTTP Геокодера Яндекса">
                </label>

                <label class="settings-form__field">
                    <span>API key Router (детали маршрута)</span>
                    <input type="text" name="yandex_router_api_key" value="{{ old('yandex_router_api_key', $yandexRouterApiKey ?? '') }}" placeholder="Введите API-ключ HTTP Router API для расчёта расстояния по дорогам (опционально)">
                </label>

                <label class="settings-form__field">
                    <span>API key Яндекс Геосаджест</span>
                    <input type="text" name="yandex_geosuggest_api_key" value="{{ old('yandex_geosuggest_api_key', $yandexGeosuggestApiKey) }}" placeholder="Введите API-ключ Geosuggest API (для подсказок города)">
                </label>

                <p class="settings-page__hint" role="note">
                    Все API-ключи в этом разделе берутся только из базы данных (`integration_settings`).
                    Значения из `.env` не используются для отображения в форме и не подмешиваются как резерв.
                </p>

                <div class="settings-form__actions">
                    <button type="submit" class="ad-btn">Сохранить</button>
                </div>
            </form>
        </section>

        <section class="settings-card" aria-label="Проверка подключения к DaData">
            <form action="{{ route('settings.dadata.test') }}" method="post" class="settings-form settings-form--inline">
                @csrf
                <label class="settings-form__field settings-form__field--grow">
                    <span>Тестовый запрос (ИНН/ОГРН или название)</span>
                    <input type="text" name="test_query" value="{{ old('test_query') }}" placeholder='Например: 7707083893 или "Газпром"' required>
                </label>
                <label class="settings-form__field settings-form__field--sm">
                    <span>Режим</span>
                    <select name="test_mode">
                        <option value="auto" @selected(old('test_mode', 'auto') === 'auto')>Авто</option>
                        <option value="inn" @selected(old('test_mode') === 'inn')>ИНН/ОГРН</option>
                        <option value="name" @selected(old('test_mode') === 'name')>Название</option>
                    </select>
                </label>
                <div class="settings-form__actions">
                    <button type="submit" class="ad-btn">Проверить и показать полный JSON</button>
                </div>
            </form>
        </section>
    </div>
@endsection
