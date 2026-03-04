@extends('layouts.app')

@section('title', 'Настройки DaData — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="settings-page">
        <header class="settings-page__head">
            <h1>Интеграция DaData</h1>
            <p>API find-party: поиск организации или ИП по ИНН/ОГРН.</p>
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
            <div class="settings-page__hint" role="status">
                <strong>Результат теста:</strong>
                <span>{{ session('dadata_test_result') }}</span>
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
                    <button type="submit" class="cp-btn cp-btn--success">Сохранить</button>
                </div>
            </form>
        </section>

        <section class="settings-card" aria-label="Проверка подключения к DaData">
            <form action="{{ route('settings.dadata.test') }}" method="post" class="settings-form settings-form--inline">
                @csrf
                <label class="settings-form__field settings-form__field--grow">
                    <span>Тестовый ИНН/ОГРН</span>
                    <input type="text" name="test_query" value="{{ old('test_query') }}" placeholder="Например: 7707083893" required>
                </label>
                <div class="settings-form__actions">
                    <button type="submit" class="cp-btn cp-btn--ghost">Проверить подключение</button>
                </div>
            </form>
        </section>
    </div>
@endsection
