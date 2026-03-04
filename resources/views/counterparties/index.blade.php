@extends('layouts.app')

@php
    $activeMenu = 'counterparties';
@endphp

@section('title', 'Контрагенты — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="cp-page">
        <div class="cp-page__top">
            <h1 class="cp-page__title">Контрагенты</h1>

            <a class="cp-btn cp-btn--success" href="{{ route('counterparties.create') }}">
                <span class="cp-btn__icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" focusable="false">
                        <path d="M10 4.17a.83.83 0 0 1 .83.83v4.17H15a.83.83 0 1 1 0 1.66h-4.17V15a.83.83 0 1 1-1.66 0v-4.17H5a.83.83 0 1 1 0-1.66h4.17V5c0-.46.37-.83.83-.83Z" fill="currentColor"/>
                    </svg>
                </span>
                <span>Новый контрагент</span>
            </a>
        </div>

        @if (session('status'))
            <div class="cp-alert cp-alert--success" role="status">{{ session('status') }}</div>
        @endif

        <form class="cp-filters" action="{{ route('counterparties.index') }}" method="get" role="search" aria-label="Фильтры контрагентов">
            <label class="cp-input cp-input--search" for="counterparties-search">
                <span class="cp-input__icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" focusable="false">
                        <path d="M9 3a6 6 0 1 1 0 12 6 6 0 0 1 0-12Zm0 1.5a4.5 4.5 0 1 0 0 9 4.5 4.5 0 0 0 0-9Zm5.56 9.99 2.97 2.97a.75.75 0 0 1-1.06 1.06l-2.97-2.97a.75.75 0 1 1 1.06-1.06Z" fill="currentColor"/>
                    </svg>
                </span>
                <input id="counterparties-search" type="search" name="search" value="{{ $search }}" placeholder="Поиск по названию, ИНН, телефону...">
            </label>

            <label class="cp-input" for="counterparties-type" aria-label="Тип контрагента">
                <select id="counterparties-type" name="type">
                    <option value="">Все типы</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->id }}" @selected((string) $type->id === $selectedType)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </label>

            <div class="cp-filters__actions">
                <button class="cp-btn cp-btn--filter" type="submit">Применить</button>
                @if ($search !== '' || $selectedType !== '')
                    <a class="cp-btn cp-btn--ghost" href="{{ route('counterparties.index') }}">Сбросить</a>
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
                                            <svg viewBox="0 0 20 20" focusable="false">
                                                <circle cx="10" cy="6.7" r="2.2" fill="none" stroke="currentColor" stroke-width="1.6"/>
                                                <path d="M5.8 15.2c0-2.2 1.9-3.8 4.2-3.8s4.2 1.6 4.2 3.8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                            </svg>
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
                                        <svg viewBox="0 0 16 16" focusable="false">
                                            <path d="M8 14s4-3.73 4-7a4 4 0 1 0-8 0c0 3.27 4 7 4 7Z" fill="none" stroke="currentColor" stroke-width="1.4"/>
                                            <circle cx="8" cy="7" r="1.6" fill="none" stroke="currentColor" stroke-width="1.4"/>
                                        </svg>
                                    </span>
                                    <span>{{ $cityText }}</span>
                                </p>
                            </td>
                            <td>
                                <p class="cp-contact">
                                    <span class="cp-inline-icon" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><path d="M5.6 2.67h1.2c.27 0 .5.18.57.44l.46 1.85a.58.58 0 0 1-.16.57L6.66 6.54a9.36 9.36 0 0 0 2.8 2.8l1.02-1.01a.58.58 0 0 1 .57-.16l1.84.46c.27.07.45.3.45.57v1.2a1 1 0 0 1-1 1A8.67 8.67 0 0 1 3.67 4.67a1 1 0 0 1 1-2Z" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg></span>
                                    <span>{{ $counterparty->phone ?: '—' }}</span>
                                </p>
                                @if (! empty($counterparty->email))
                                    <p class="cp-contact"><span class="cp-inline-icon" aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><rect x="2.5" y="3.5" width="11" height="9" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.2"/><path d="m3.5 5 4.5 3.5L12.5 5" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>{{ $counterparty->email }}</span></p>
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
