@extends('layouts.app')

@php
    $activeMenu = 'counterparties';
@endphp

@section('title', 'Контрагенты — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="cp-page">
        <div class="cp-page__top">
            <h1 class="ad-h1">Контрагенты</h1>

            <a class="ad-btn" href="{{ route('counterparties.create') }}">
                <span aria-hidden="true">
                    <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-doc-plus"></use></svg>
                </span>
                <span>Новый контрагент</span>
            </a>
        </div>

        @if (session('status'))
            <div class="cp-alert cp-alert--success" role="status">{{ session('status') }}</div>
        @endif

        <form class="cp-filters" action="{{ route('counterparties.index') }}" method="get" role="search" aria-label="Фильтры контрагентов" data-counterparty-search-form data-counterparty-suggest-url="{{ route('counterparties.search.suggest') }}">
            <label class="cp-input cp-input--search" for="counterparties-search">
                <span class="cp-input__icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-search"></use></svg>
                </span>
                <input id="counterparties-search" type="search" name="search" value="{{ $search }}" placeholder="Поиск по названию, ИНН, телефону...">
                <div class="cp-search-suggest" data-counterparty-suggest-results hidden></div>
            </label>
            <p class="cp-search-suggest__status" data-counterparty-suggest-status hidden></p>

            <label class="cp-input" for="counterparties-type" aria-label="Тип контрагента">
                <select id="counterparties-type" name="type">
                    <option value="">Все типы</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected((string) $type->id === $selectedType)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="cp-filters__actions">
                <button class="ad-btn" type="submit">Применить</button>
                @if ($search !== '' || $selectedType !== '')
                    <a class="ad-btn" href="{{ route('counterparties.index') }}">Сбросить</a>
                @endif
            </div>
        </form>

        <section class="cp-stats" aria-label="Статистика по контрагентам">
            <article class="cp-stat-card">
                <p class="cp-stat-card__value">{{ $allCount }}</p>
                <p class="cp-stat-card__label">Всего контрагентов</p>
            </article>

            <article class="cp-stat-card">
                <p class="cp-stat-card__value is-blue">{{ $legalCount }}</p>
                <p class="cp-stat-card__label">Юридических лиц</p>
            </article>
        </section>

        <section class="cp-table-wrap" aria-label="Список контрагентов">
            <table class="cp-table">
                <thead>
                    <tr>
                        <th>КОНТРАГЕНТ</th>
                        <th>ТИП</th>
                        <th>ГОРОД</th>
                        <th>КОНТАКТЫ</th>
                        <th>ДОБАВЛЕН</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($counterparties as $counterparty)
                        @php
                            $counterpartyName = $counterparty->short_name ?: $counterparty->full_name ?: 'Без названия';
                            $typeName = $counterparty->typeRef?->name ?? '—';
                            $isPersonType = str_contains(mb_strtolower($typeName), 'физ') || str_contains(mb_strtolower($typeName), 'самозан');
                            $cityText = $counterparty->actual_address ?: $counterparty->legal_address ?: '—';
                        @endphp
                        <tr class="cp-row-clickable" role="link" tabindex="0" aria-label="Открыть карточку контрагента {{ $counterpartyName }}" data-href="{{ route('counterparties.show', $counterparty) }}" onclick="window.location=this.dataset.href" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location=this.dataset.href; }">
                            <td>
                                <div class="cp-entity">
                                    <span class="cp-entity__icon {{ $isPersonType ? 'is-person' : 'is-legal' }}" aria-hidden="true">
                                        @if ($isPersonType)
                                            <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                                        @else
                                            <svg viewBox="0 0 20 20" focusable="false">
                                                <use href="/icons/sprite.svg#icon-counterparties"></use>
                                            </svg>
                                        @endif
                                    </span>
                                    <div>
                                        <p class="cp-entity__name">{{ $counterpartyName }}</p>
                                        @if (! empty($counterparty->inn))
                                            <p class="cp-entity__meta">ИНН: {{ $counterparty->inn }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="cp-muted">{{ $typeName }}</td>
                            <td>
                                <p class="cp-city">
                                    <span class="cp-inline-icon" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                                    </span>
                                    <span>{{ $cityText }}</span>
                                </p>
                            </td>
                            <td>
                                <p class="cp-contact">
                                    <span class="cp-inline-icon" aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg></span>
                                    <span>{{ $counterparty->phone ?: '—' }}</span>
                                </p>
                                @if (! empty($counterparty->email))
                                    <p class="cp-contact"><span class="cp-inline-icon" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-auth-mail"></use></svg></span><span>{{ $counterparty->email }}</span></p>
                                @endif
                            </td>
                            <td class="cp-date">{{ optional($counterparty->created_at)->format('d.m.Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="cp-empty">Контрагентов пока нет. Добавьте первого контрагента, чтобы увидеть список.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($counterparties->hasPages())
                <div class="cp-pagination" aria-label="Навигация по страницам">
                    @if ($counterparties->onFirstPage())
                        <span class="cp-pagination__btn is-disabled">Назад</span>
                    @else
                        <a class="cp-pagination__btn" href="{{ $counterparties->previousPageUrl() }}">Назад</a>
                    @endif

                    <span class="cp-pagination__meta">Страница {{ $counterparties->currentPage() }} из {{ $counterparties->lastPage() }}</span>

                    @if ($counterparties->hasMorePages())
                        <a class="cp-pagination__btn" href="{{ $counterparties->nextPageUrl() }}">Вперёд</a>
                    @else
                        <span class="cp-pagination__btn is-disabled">Вперёд</span>
                    @endif
                </div>
            @endif
        </section>
    </div>
@endsection
