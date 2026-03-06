@extends('layouts.app')

{{--
    Универсальный шаблон карточки контрагента.
    Поддерживает 3 режима: show/create/edit.
--}}
@php
    $mode = $mode ?? 'show';
    $isEditingMode = in_array($mode, ['create', 'edit'], true);
    $hasPersistedCounterparty = ! empty($counterparty->id) && (bool) $counterparty->exists;
    $editingHeading = $mode === 'create' ? 'Создание контрагента' : 'Редактирование контрагента';
    $activeMenu = 'counterparties';
    $counterpartyName = $counterparty->short_name ?: $counterparty->full_name ?: 'Контрагент';
    $typeName = $counterparty->typeRef?->name ?? '—';
    $isPersonType = str_contains(mb_strtolower($typeName), 'физ') || str_contains(mb_strtolower($typeName), 'самозан');
    $bankName = $primaryBankAccount?->bank?->name;
    $bankBik = $primaryBankAccount?->bank?->bik;
    $bankCorr = $primaryBankAccount?->bank?->correspondent_account;
    $bankAccount = $primaryBankAccount?->account_number;
    $tabBaseRoute = $mode === 'edit' && $hasPersistedCounterparty
        ? route('counterparties.edit', ['counterparty' => $counterparty])
        : ($mode === 'create'
            ? route('counterparties.create')
            : ($hasPersistedCounterparty ? route('counterparties.show', ['counterparty' => $counterparty]) : '#'));
@endphp

@section('title', ($isEditingMode ? $editingHeading : 'Контрагент') . ' — ' . config('app.name', 'Авто Доставка'))

@section('content')
    <div class="cp-view">
        @if (session('status'))
            <div class="cp-alert cp-alert--success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="cp-create__errors" role="alert">
                <p>Не удалось сохранить данные:</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Верхняя панель: навигация назад + кнопка "Редактировать" (только show-режим). --}}
        <div class="cp-view__top">
            <a class="cp-view__back" href="{{ $isEditingMode && $mode === 'edit' ? route('counterparties.show', $counterparty) : route('counterparties.index') }}">
                <span class="cp-view__back-icon" aria-hidden="true">
                    <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-left-arrow"></use></svg>
                </span>
                <span>{{ $isEditingMode && $mode === 'edit' ? 'Вернуться к карточке' : 'Вернуться к списку' }}</span>
            </a>

            @unless ($isEditingMode)
                <a class="ad-btn" href="{{ route('counterparties.edit', $counterparty) }}">
                    <span aria-hidden="true">
                        <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-doc-edit"></use></svg>
                    </span>
                    <span>Редактировать</span>
                </a>
            @endunless
        </div>

        <div class="cp-view__grid">
            <div class="cp-view__main">
                {{-- Карточка с базовой информацией и иконкой типа контрагента. --}}
                <section class="cp-view-card cp-company" aria-label="Информация о компании">
                    <div class="cp-company__icon" aria-hidden="true">
                        @if ($isPersonType)
                            <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                        @else
                            <svg viewBox="0 0 20 20" focusable="false">
                                <use href="/icons/sprite.svg#icon-counterparties"></use>
                            </svg>
                        @endif
                    </div>

                    <div class="cp-company__body">
                        @if ($isEditingMode)
                            <h1 class="ad-h1">{{ $editingHeading }}</h1>
                            <p>{{ $counterpartyName }}</p>
                        @else
                            <h1 class="ad-h1">{{ $counterpartyName }}</h1>
                            <p>
                                <span>{{ $typeName }}</span>
                                <span>•</span>
                                <span>ИНН: {{ $counterparty->inn ?: '—' }}</span>
                                <span>•</span>
                                <span>ОГРН: {{ $counterparty->ogrn ?: '—' }}</span>
                            </p>
                        @endif
                    </div>
                </section>

                @if ($isEditingMode)
                    {{--
                        Визуальный селектор типа контрагента.
                        Список типов берётся динамически из таблицы counterparties_type (переменная $types).
                        Реальное значение сохраняется через hidden input name="type" в формах вкладок.
                    --}}
                    <section class="cp-view-card cp-company-type" aria-label="Тип контрагента">
                        @php
                            // Берём old('type') после ошибки валидации, иначе текущее значение из модели.
                            // Если в create ещё пусто — используем запасной id по умолчанию.
                            $selectedTypeId = (string) old('type', $counterparty->type ?: $legalTypeId ?: $personTypeId);
                        @endphp

                        @forelse ($types as $typeOption)
                            @php
                                $typeId = (string) $typeOption->id;
                                $isActiveType = $selectedTypeId === $typeId;
                                $normalizedTypeName = mb_strtolower(trim((string) $typeOption->name));
                                $typeKind = 'legal';

                                if (str_contains($normalizedTypeName, 'физ') || str_contains($normalizedTypeName, 'самозан')) {
                                    $typeKind = 'person';
                                } elseif (
                                    preg_match('/(^|\s|[^а-яa-z])ип($|\s|[^а-яa-z])/u', $normalizedTypeName) === 1
                                    || str_contains($normalizedTypeName, 'индивидуал')
                                    || str_contains($normalizedTypeName, 'предприним')
                                ) {
                                    $typeKind = 'entrepreneur';
                                }
                            @endphp
                            <label
                                class="cp-company-type__chip {{ $isActiveType ? 'is-active' : '' }}"
                                data-type-chip="{{ $typeOption->id }}"
                                data-type-kind="{{ $typeKind }}"
                                data-type-name="{{ $typeOption->name }}"
                            >
                                <input type="radio" name="cp_type_visual" value="{{ $typeOption->id }}" {{ $isActiveType ? 'checked' : '' }}>
                                <span>{{ $typeOption->name }}</span>
                            </label>
                        @empty
                            <p>Типы контрагентов не настроены.</p>
                        @endforelse
                    </section>
                @endif

                <section class="cp-view-card cp-tabs-card" aria-label="Общие сведения">
                    {{--
                        Навигация по вкладкам.
                        Вкладка хранится в query-параметре tab: general|banking|contacts.
                    --}}
                    <nav class="cp-tabs" aria-label="Разделы контрагента">
                        <a class="{{ $activeTab === 'general' ? 'is-active' : '' }}" href="{{ $tabBaseRoute !== '#' ? $tabBaseRoute.'?tab=general' : '#' }}">
                            <span class="cp-tab-icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-orders"></use></svg>
                            </span>
                            <span>Общие сведения</span>
                        </a>
                        <a class="{{ $activeTab === 'banking' ? 'is-active' : '' }}" href="{{ $tabBaseRoute !== '#' ? $tabBaseRoute.'?tab=banking' : '#' }}">
                            <span class="cp-tab-icon" aria-hidden="true">
                                <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-doc-creditcard"></use></svg>
                            </span>
                            <span>Банковские реквизиты</span>
                        </a>
                        <a class="{{ $activeTab === 'contacts' ? 'is-active' : '' }}" href="{{ $tabBaseRoute !== '#' ? $tabBaseRoute.'?tab=contacts' : '#' }}">
                            <span class="cp-tab-icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                            </span>
                            <span>Контакты</span>
                            <span class="cp-badge">{{ $contactsCount }}</span>
                        </a>
                    </nav>

                    <div class="cp-tab-content">
                        {{--
                            Режимы рендера контента вкладок:
                            - create/edit: показываем формы (partials)
                            - show: показываем readonly-блоки
                        --}}
                        @if ($isEditingMode && $activeTab === 'banking')
                            @include('counterparties.partials.form-banking', ['mode' => $mode, 'counterparty' => $counterparty, 'bankName' => $bankName, 'bankBik' => $bankBik, 'bankCorr' => $bankCorr, 'bankAccount' => $bankAccount])
                        @elseif ($isEditingMode && $activeTab === 'contacts')
                            @include('counterparties.partials.form-contacts', ['mode' => $mode, 'counterparty' => $counterparty])
                        @elseif ($isEditingMode)
                            @include('counterparties.partials.form', ['mode' => $mode, 'counterparty' => $counterparty, 'types' => $types])
                        @elseif ($activeTab === 'banking')
                            <section class="cp-bank" aria-label="Банковские реквизиты">
                                <div class="cp-bank__head">
                                    <span class="cp-bank__icon" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-landmark"></use></svg>
                                    </span>
                                    <div>
                                        <p>Банк</p>
                                        <strong>{{ $bankName ?: 'Не указан' }}</strong>
                                    </div>
                                </div>

                                <div class="cp-bank__panel">
                                    <div class="cp-bank__row">
                                        <p>БИК</p>
                                        <div>
                                            <strong>{{ $bankBik ?: '—' }}</strong>
                                            <button type="button" class="ad-btn" data-copy-text="{{ $bankBik ?: '' }}" aria-label="Скопировать БИК">
                                                <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-copy"></use></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="cp-bank__row">
                                        <p>Расчётный счёт</p>
                                        <div>
                                            <strong>{{ $bankAccount ?: '—' }}</strong>
                                            <button type="button" class="ad-btn" data-copy-text="{{ $bankAccount ?: '' }}" aria-label="Скопировать расчётный счёт">
                                                <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-copy"></use></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="cp-bank__row">
                                        <p>Корреспондентский счёт</p>
                                        <div>
                                            <strong>{{ $bankCorr ?: '—' }}</strong>
                                            <button type="button" class="ad-btn" data-copy-text="{{ $bankCorr ?: '' }}" aria-label="Скопировать корреспондентский счёт">
                                                <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-copy"></use></svg>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="cp-bank__row">
                                        <p>КПП</p>
                                        <div>
                                            <strong>{{ $counterparty->kpp ?: '—' }}</strong>
                                            <button type="button" class="ad-btn" data-copy-text="{{ $counterparty->kpp ?: '' }}" aria-label="Скопировать КПП">
                                                <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-copy"></use></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="cp-bank-copy">
                                    <div class="cp-bank-copy__top">
                                        <h2>Все реквизиты для копирования</h2>
                                        <button type="button" class="ad-btn" data-copy-target="#cp-bank-copy-text" aria-label="Скопировать все реквизиты">
                                            <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-copy"></use></svg>
                                        </button>
                                    </div>
                                    <pre id="cp-bank-copy-text">{{ $bankCopyText }}</pre>
                                </div>
                            </section>
                        @elseif ($activeTab === 'contacts')
                            <section class="cp-contacts" aria-label="Контакты контрагента">
                                <div class="cp-contacts__head">
                                    <p>{{ $contactsCount }} {{ trans_choice('контакт|контакта|контактов', $contactsCount) }}</p>
                                    <button type="button" class="ad-btn">
                                        <span aria-hidden="true">
                                            <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-doc-plus"></use></svg>
                                        </span>
                                        <span>Добавить</span>
                                    </button>
                                </div>

                                <div class="cp-contacts__grid">
                                    @forelse ($counterparty->contacts as $contact)
                                        <article class="cp-contact-card">
                                            <div class="cp-contact-card__top">
                                                <span class="cp-contact-card__avatar" aria-hidden="true">
                                                    <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-man"></use></svg>
                                                </span>
                                                <div class="cp-contact-card__identity">
                                                    <div class="cp-contact-card__name-row">
                                                        <h3>{{ $contact->full_name }}</h3>
                                                        @if ($contact->is_primary)
                                                            <span class="cp-contact-card__primary">
                                                                <span aria-hidden="true">☆</span>
                                                                <span>Основной</span>
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <p class="cp-contact-card__role">
                                                        <span aria-hidden="true">
                                                            <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-doc-briefcase"></use></svg>
                                                        </span>
                                                        <span>{{ $contact->notes ?: 'Контактное лицо' }}</span>
                                                    </p>
                                                </div>
                                            </div>

                                            @if ($contact->phone_mobile || $contact->phone_city)
                                                <a class="cp-contact-card__link is-phone" href="tel:{{ preg_replace('/\D+/', '', $contact->phone_mobile ?: $contact->phone_city) }}">
                                                    <span aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg></span>
                                                    <span>{{ $contact->phone_mobile ?: $contact->phone_city }}</span>
                                                </a>
                                            @endif

                                            @if ($contact->email)
                                                <a class="cp-contact-card__link" href="mailto:{{ $contact->email }}">
                                                    <span aria-hidden="true"><svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-auth-mail"></use></svg></span>
                                                    <span>{{ $contact->email }}</span>
                                                </a>
                                            @endif
                                        </article>
                                    @empty
                                        <article class="cp-contact-card cp-contact-card--empty">
                                            <p>Контакты пока не добавлены.</p>
                                        </article>
                                    @endforelse
                                </div>
                            </section>
                        @else
                        <section class="cp-section">
                            <h2>Основная информация</h2>

                            <div class="cp-info-list">
                                <div class="cp-info-item">
                                    <span class="cp-info-icon is-blue" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-counterparties"></use></svg>
                                    </span>
                                    <div>
                                        <p>Полное наименование</p>
                                        <strong>{{ $counterparty->full_name ?: '—' }}</strong>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="cp-section">
                            <h2>Контактная информация</h2>

                            <div class="cp-info-list">
                                <div class="cp-info-item">
                                    <span class="cp-info-icon is-blue" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-phone"></use></svg>
                                    </span>
                                    <div>
                                        <p>Телефон</p>
                                        <strong>{{ $counterparty->phone ?: '—' }}</strong>
                                    </div>
                                </div>

                                <div class="cp-info-item">
                                    <span class="cp-info-icon is-blue" aria-hidden="true">
                                        <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-auth-mail"></use></svg>
                                    </span>
                                    <div>
                                        <p>Email</p>
                                        <strong>{{ $counterparty->email ?: '—' }}</strong>
                                    </div>
                                </div>

                                <div class="cp-info-item">
                                    <span class="cp-info-icon is-green" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-place"></use></svg>
                                    </span>
                                    <div>
                                        <p>Юридический адрес</p>
                                        <strong>{{ $counterparty->legal_address ?: '—' }}</strong>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="cp-section">
                            <h2>Реквизиты</h2>
                            <div class="cp-doc-grid">
                                <article>
                                    <p>ИНН</p>
                                    <div>
                                        <strong>{{ $counterparty->inn ?: '—' }}</strong>
                                        <button type="button" class="ad-btn" data-copy-text="{{ $counterparty->inn ?: '' }}" aria-label="Скопировать ИНН">
                                            <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-copy"></use></svg>
                                        </button>
                                    </div>
                                </article>

                                <article>
                                    <p>ОГРН</p>
                                    <div>
                                        <strong>{{ $counterparty->ogrn ?: '—' }}</strong>
                                        <button type="button" class="ad-btn" data-copy-text="{{ $counterparty->ogrn ?: '' }}" aria-label="Скопировать ОГРН">
                                            <svg viewBox="0 0 16 16" focusable="false"><use href="/icons/sprite.svg#icon-copy"></use></svg>
                                        </button>
                                    </div>
                                </article>
                            </div>
                        </section>

                        <section class="cp-section">
                            <h2>Примечания</h2>
                            <div class="cp-note">{{ $counterparty->notes ?: 'Примечания отсутствуют' }}</div>
                        </section>
                        @endif
                    </div>
                </section>
            </div>

            <aside class="cp-view__side" aria-label="Боковая информация">
                @if ($isEditingMode)
                    {{--
                        Блок действий в режиме create/edit.
                        Кнопка submit отправляет активную форму вкладки через form="cp-counterparty-form".
                    --}}
                    <section class="cp-view-card cp-side-card cp-side-actions">
                        <h2>Действия</h2>
                        <button class="ad-btn" type="submit" form="cp-counterparty-form">Сохранить изменения</button>
                        <a class="ad-btn" href="{{ $mode === 'edit' ? route('counterparties.show', $counterparty) : route('counterparties.index') }}">Отмена</a>
                        @if ($mode === 'edit')
                            <button class="ad-btn" type="button">Удалить контрагента</button>
                        @endif
                    </section>
                @endif

                <section class="cp-view-card cp-side-card">
                    {{-- Краткая сводка заполненности ключевых сущностей. --}}
                    <h2>Сводка</h2>

                    <div class="cp-summary-row">
                        <span>Банк. реквизиты</span>
                        <span class="cp-chip {{ $primaryBankAccount ? 'cp-chip--ok' : 'cp-chip--warn' }}">{{ $primaryBankAccount ? '✓ Заполнены' : 'Не заполнены' }}</span>
                    </div>
                    <div class="cp-summary-row">
                        <span>Контакты</span>
                        <span class="cp-chip cp-chip--info">{{ $contactsCount }}</span>
                    </div>
                </section>

                <section class="cp-view-card cp-side-card">
                    {{-- Техническая/служебная информация о записи. --}}
                    <h2>Информация</h2>

                    <div class="cp-side-item">
                        <span aria-hidden="true">
                            <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-date"></use></svg>
                        </span>
                        <div>
                            <p>Добавлен</p>
                            <strong>{{ optional($counterparty->created_at)->format('d.m.Y') ?: '—' }}</strong>
                        </div>
                    </div>

                    <div class="cp-side-item">
                        <span aria-hidden="true"><svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-orders"></use></svg></span>
                        <div>
                            <p>ID</p>
                            <strong>#{{ $counterparty->id ?: '—' }}</strong>
                        </div>
                    </div>
                </section>

                @unless ($isEditingMode)
                    <section class="cp-view-card cp-side-card">
                        <h2>Быстрые действия</h2>
                        <a class="ad-btn" href="{{ route('orders.create') }}">Создать заявку</a>
                        <button class="ad-btn" type="button">История заявок</button>
                    </section>
                @endunless
            </aside>
        </div>
    </div>

    @if ($isEditingMode)
        {{--
            Управление визуальным селектором типа.
            По клику синхронизируется активный чип, radio и hidden input'ы type
            во всех формах вкладок.
        --}}
        <script>
            const applyTypeKindToForm = (kind) => {
                const normalizedKind = kind || 'legal';

                document.querySelectorAll('[data-type-visible-kinds]').forEach((node) => {
                    const rawKinds = node.getAttribute('data-type-visible-kinds') || '';
                    const kinds = rawKinds
                        .split(',')
                        .map((item) => item.trim())
                        .filter(Boolean);

                    const shouldShow = kinds.length === 0 || kinds.includes(normalizedKind);
                    node.hidden = !shouldShow;
                    node.style.display = shouldShow ? '' : 'none';

                    if (!shouldShow) {
                        node.querySelectorAll('input[data-clear-when-hidden="1"], textarea[data-clear-when-hidden="1"]').forEach((input) => {
                            input.value = '';
                        });
                    }
                });

                document.querySelectorAll('[data-required-kinds]').forEach((input) => {
                    const rawKinds = input.getAttribute('data-required-kinds') || '';
                    const requiredKinds = rawKinds
                        .split(',')
                        .map((item) => item.trim())
                        .filter(Boolean);

                    input.required = requiredKinds.includes(normalizedKind);
                });

                document.querySelectorAll('[data-placeholder-legal], [data-placeholder-entrepreneur], [data-placeholder-person]').forEach((input) => {
                    const placeholderByKind = input.getAttribute(`data-placeholder-${normalizedKind}`);
                    if (placeholderByKind !== null) {
                        input.placeholder = placeholderByKind;
                    }
                });
            };

            const getActiveTypeKind = () => {
                const activeChip = document.querySelector('[data-type-chip].is-active')
                    || document.querySelector('[data-type-chip] input[type="radio"]:checked')?.closest('[data-type-chip]')
                    || document.querySelector('[data-type-chip]');

                return activeChip?.dataset.typeKind || 'legal';
            };

            document.querySelectorAll('[data-type-chip]').forEach((chip) => {
                chip.addEventListener('click', () => {
                    document.querySelectorAll('[data-type-chip]').forEach((item) => item.classList.remove('is-active'));
                    chip.classList.add('is-active');

                    const radio = chip.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                    }

                    document.querySelectorAll('[data-counterparty-type-input]').forEach((hiddenInput) => {
                        hiddenInput.value = chip.dataset.typeChip;
                    });

                    applyTypeKindToForm(chip.dataset.typeKind);
                });
            });

            applyTypeKindToForm(getActiveTypeKind());
        </script>
    @endif
@endsection
