@php
    $isCreateMode = $mode === 'create';
    $formAction = $isCreateMode ? route('counterparties.store') : route('counterparties.update', $counterparty);
    $legalAddressDataValue = old('legal_address_data', is_array($counterparty->legal_address_data ?? null)
        ? json_encode($counterparty->legal_address_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : ($counterparty->legal_address_data ?? ''));
@endphp

<form id="cp-counterparty-form" class="cp-inline-form" action="{{ $formAction }}" method="post">
    @csrf
    @if (! $isCreateMode)
        @method('PUT')
    @endif

    <div class="cp-inline-section">
        <h2>Основная информация</h2>
        <label class="cp-inline-form__field cp-inline-form__field--full cp-create-search" data-type-visible-kinds="legal" data-counterparty-create-search data-counterparty-suggest-url="{{ route('counterparties.search.suggest') }}">
            <span data-type-visible-kinds="legal">Название организации <em>*</em></span>
            <input
                type="search"
                name="short_name"
                value="{{ old('short_name', $counterparty->short_name) }}"
                placeholder='Начните вводить название компании или ИП...'
                autocomplete="off"
                data-required-kinds="legal"
                data-clear-when-hidden="1"
                data-counterparty-create-search-input
            >
            <small class="cp-inline-form__hint">Начните вводить название — покажем совпадения из базы и DaData. При выборе найденной организации форма заполнится автоматически.</small>
            <div class="cp-search-suggest cp-search-suggest--form" data-counterparty-create-search-results hidden></div>
            <p class="cp-search-suggest__status" data-counterparty-create-search-status hidden></p>
        </label>

        <label class="cp-inline-form__field cp-inline-form__field--full">
            <span data-type-visible-kinds="legal">Полное наименование</span>
            <span data-type-visible-kinds="entrepreneur,person">ФИО <em>*</em></span>
            <input
                type="text"
                name="full_name"
                value="{{ old('full_name', $counterparty->full_name) }}"
                placeholder='Общество с ограниченной ответственностью "Название компании"'
                data-required-kinds="entrepreneur,person"
                data-placeholder-legal='Общество с ограниченной ответственностью "Название компании"'
                data-placeholder-entrepreneur="Иванов Иван Иванович"
                data-placeholder-person="Иванов Иван Иванович"
            >
        </label>

        <div class="cp-inline-form__grid">
            <label class="cp-inline-form__field">
                <span>ИНН</span>
                <div class="cp-inline-form__input-with-action">
                    <input type="text" name="inn" value="{{ old('inn', $counterparty->inn) }}" placeholder="7701234567" data-dadata-query-input>
                    <button type="button" class="ad-btn" data-dadata-fill-btn>DaData</button>
                </div>
                <small class="cp-inline-form__hint">Вставьте ИНН/ОГРН и нажмите DaData (или просто выйдите из поля).</small>
                <p class="cp-dadata-fill__status" data-dadata-fill-status hidden></p>
            </label>
            <label class="cp-inline-form__field">
                <span data-type-visible-kinds="legal">ОГРН</span>
                <span data-type-visible-kinds="entrepreneur">ОГРНИП</span>
                <span data-type-visible-kinds="person">ОГРН / ОГРНИП</span>
                <input type="text" name="ogrn" value="{{ old('ogrn', $counterparty->ogrn) }}" placeholder="1027700132195">
            </label>
        </div>
    </div>

    <div class="cp-inline-section">
        <h2>Контактная информация</h2>
        <div class="cp-inline-form__grid">
            <label class="cp-inline-form__field cp-inline-form__field--icon">
                <span>Телефон <em>*</em></span>
                <input type="text" name="phone" value="{{ old('phone', $counterparty->phone) }}" placeholder="+7 (999) 123-45-67" required>
            </label>
            <label class="cp-inline-form__field cp-inline-form__field--icon">
                <span>Email</span>
                <input type="email" name="email" value="{{ old('email', $counterparty->email) }}" placeholder="email@example.com">
            </label>
        </div>
    </div>

    <div class="cp-inline-section">
        <h2>Адрес</h2>
        <div class="cp-inline-form__grid">
            <label class="cp-inline-form__field cp-inline-form__field--icon">
                <span>Город <em>*</em></span>
                <input type="text" name="actual_address" value="{{ old('actual_address', $counterparty->actual_address) }}" placeholder="Москва" required>
            </label>
            <label class="cp-inline-form__field">
                <span>Юридический адрес <em>*</em></span>
                <input type="text" name="legal_address" value="{{ old('legal_address', $counterparty->legal_address) }}" placeholder="ул. Ленина, 12" required>
            </label>
        </div>
    </div>

    <div class="cp-inline-section">
        <h2>Примечания</h2>
        <label class="cp-inline-form__field cp-inline-form__field--full">
            <textarea name="notes" rows="4" placeholder="Дополнительная информация о контрагенте...">{{ old('notes', $counterparty->notes) }}</textarea>
        </label>
    </div>

    <input id="cp-type-hidden" data-counterparty-type-input type="hidden" name="type" value="{{ old('type', $counterparty->type) }}">
    <input type="hidden" name="kpp" value="{{ old('kpp', $counterparty->kpp) }}">
    <input type="hidden" name="legal_postal_code" value="{{ old('legal_postal_code', $counterparty->legal_postal_code) }}">
    <input type="hidden" name="legal_region" value="{{ old('legal_region', $counterparty->legal_region) }}">
    <input type="hidden" name="legal_city" value="{{ old('legal_city', $counterparty->legal_city) }}">
    <input type="hidden" name="legal_settlement" value="{{ old('legal_settlement', $counterparty->legal_settlement) }}">
    <input type="hidden" name="legal_street" value="{{ old('legal_street', $counterparty->legal_street) }}">
    <input type="hidden" name="legal_house" value="{{ old('legal_house', $counterparty->legal_house) }}">
    <input type="hidden" name="legal_block" value="{{ old('legal_block', $counterparty->legal_block) }}">
    <input type="hidden" name="legal_flat" value="{{ old('legal_flat', $counterparty->legal_flat) }}">
    <input type="hidden" name="legal_fias_id" value="{{ old('legal_fias_id', $counterparty->legal_fias_id) }}">
    <input type="hidden" name="legal_kladr_id" value="{{ old('legal_kladr_id', $counterparty->legal_kladr_id) }}">
    <input type="hidden" name="legal_geo_lat" value="{{ old('legal_geo_lat', $counterparty->legal_geo_lat) }}">
    <input type="hidden" name="legal_geo_lon" value="{{ old('legal_geo_lon', $counterparty->legal_geo_lon) }}">
    <input type="hidden" name="legal_qc" value="{{ old('legal_qc', $counterparty->legal_qc) }}">
    <input type="hidden" name="legal_qc_geo" value="{{ old('legal_qc_geo', $counterparty->legal_qc_geo) }}">
    <input type="hidden" name="legal_address_invalid" value="{{ old('legal_address_invalid', $counterparty->legal_address_invalid === null ? '' : ($counterparty->legal_address_invalid ? '1' : '0')) }}">
    <input type="hidden" name="legal_address_data" value="{{ $legalAddressDataValue }}">
</form>

<script>
    (() => {
        const form = document.getElementById('cp-counterparty-form');
        if (!form) {
            return;
        }

        const createSearch = form.querySelector('[data-counterparty-create-search]');
        const createSearchInput = createSearch?.querySelector('[data-counterparty-create-search-input]');
        const createSearchResults = createSearch?.querySelector('[data-counterparty-create-search-results]');
        const createSearchStatus = createSearch?.querySelector('[data-counterparty-create-search-status]');
        const createSuggestUrl = createSearch?.dataset.counterpartySuggestUrl || '';
        const fillButton = form.querySelector('[data-dadata-fill-btn]');
        const statusNode = form.querySelector('[data-dadata-fill-status]');
        const queryInput = form.querySelector('[data-dadata-query-input]') || form.querySelector('input[name="inn"]');
        const endpoint = '{{ route('counterparties.dadata.autofill') }}';
        const validQueryLengths = [10, 12, 13, 15];
        let isLoading = false;
        let lastLoadedQuery = '';
        let createSearchTimer = null;

        const fieldMap = [
            ['short_name', 'short_name', false],
            ['full_name', 'full_name', false],
            ['inn', 'inn', false],
            ['ogrn', 'ogrn', false],
            ['kpp', 'kpp', false],
            ['phone', 'phone', false],
            ['legal_address', 'legal_address', false],
            ['legal_postal_code', 'legal_postal_code', true],
            ['legal_region', 'legal_region', true],
            ['legal_city', 'legal_city', true],
            ['legal_settlement', 'legal_settlement', true],
            ['legal_street', 'legal_street', true],
            ['legal_house', 'legal_house', true],
            ['legal_block', 'legal_block', true],
            ['legal_flat', 'legal_flat', true],
            ['legal_fias_id', 'legal_fias_id', true],
            ['legal_kladr_id', 'legal_kladr_id', true],
            ['legal_geo_lat', 'legal_geo_lat', true],
            ['legal_geo_lon', 'legal_geo_lon', true],
            ['legal_qc', 'legal_qc', true],
            ['legal_qc_geo', 'legal_qc_geo', true],
            ['legal_address_invalid', 'legal_address_invalid', true],
            ['legal_address_data', 'legal_address_data', true],
        ];

        if (!fillButton || !queryInput) {
            return;
        }

        const showStatus = (message, state = 'info') => {
            if (!statusNode) {
                return;
            }

            statusNode.hidden = false;
            statusNode.classList.remove('is-success', 'is-error');

            if (state === 'success') {
                statusNode.classList.add('is-success');
            }

            if (state === 'error') {
                statusNode.classList.add('is-error');
            }

            statusNode.textContent = message;
        };

        const serializeValue = (value) => {
            if (value === null || value === undefined) {
                return '';
            }

            if (typeof value === 'object') {
                try {
                    return JSON.stringify(value);
                } catch (error) {
                    return '';
                }
            }

            if (typeof value === 'boolean') {
                return value ? '1' : '0';
            }

            return `${value}`;
        };

        const setFieldValue = (name, value, { clearWhenEmpty = false } = {}) => {
            const input = form.querySelector(`[name="${name}"]`);
            if (!input) {
                return;
            }

            const serialized = serializeValue(value);

            if (serialized === '' && !clearWhenEmpty) {
                return;
            }

            input.value = serialized;
        };

        const clearLegalAddressMeta = () => {
            fieldMap
                .filter(([payloadKey]) => payloadKey.startsWith('legal_') && payloadKey !== 'legal_address')
                .forEach(([, fieldName]) => setFieldValue(fieldName, '', { clearWhenEmpty: true }));
        };

        const applyCounterpartyData = (data = {}) => {
            activateTypeByKind(data.type_kind || 'legal');

            fieldMap.forEach(([payloadKey, fieldName, clearWhenEmpty]) => {
                setFieldValue(fieldName, data[payloadKey], { clearWhenEmpty });
            });
        };

        const activateTypeByKind = (kind) => {
            if (!kind) {
                return;
            }

            const typeChip = document.querySelector(`[data-type-chip][data-type-kind="${kind}"]`);
            if (typeChip) {
                typeChip.click();
            }
        };

        const loadFromDaData = async ({ silentInvalid = false } = {}) => {
            const rawQuery = String(queryInput.value || '').trim();
            const normalizedQuery = rawQuery.replace(/\D+/g, '');

            if (!validQueryLengths.includes(normalizedQuery.length)) {
                if (!silentInvalid) {
                    showStatus('Введите корректный ИНН или ОГРН (10, 12, 13 или 15 цифр).', 'error');
                    queryInput.focus();
                }
                return;
            }

            if (isLoading || normalizedQuery === lastLoadedQuery) {
                return;
            }

            const token = form.querySelector('input[name="_token"]')?.value || '';
            const payload = new URLSearchParams();
            payload.append('_token', token);
            payload.append('query', normalizedQuery);

            isLoading = true;
            fillButton.disabled = true;
            showStatus('Загружаем данные из DaData...');

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: payload.toString(),
                });

                const json = await response.json();

                if (!response.ok) {
                    throw new Error(json?.message || 'Не удалось получить данные из DaData.');
                }

                const data = json?.data || {};

                activateTypeByKind(data.type_kind || 'legal');

                setFieldValue('short_name', data.short_name);
                setFieldValue('full_name', data.full_name);
                setFieldValue('inn', data.inn);
                setFieldValue('ogrn', data.ogrn);
                setFieldValue('kpp', data.kpp);
                setFieldValue('legal_address', data.legal_address);
                setFieldValue('phone', data.phone);
                setFieldValue('legal_postal_code', data.legal_postal_code, { clearWhenEmpty: true });
                setFieldValue('legal_region', data.legal_region, { clearWhenEmpty: true });
                setFieldValue('legal_city', data.legal_city, { clearWhenEmpty: true });
                setFieldValue('legal_settlement', data.legal_settlement, { clearWhenEmpty: true });
                setFieldValue('legal_street', data.legal_street, { clearWhenEmpty: true });
                setFieldValue('legal_house', data.legal_house, { clearWhenEmpty: true });
                setFieldValue('legal_block', data.legal_block, { clearWhenEmpty: true });
                setFieldValue('legal_flat', data.legal_flat, { clearWhenEmpty: true });
                setFieldValue('legal_fias_id', data.legal_fias_id, { clearWhenEmpty: true });
                setFieldValue('legal_kladr_id', data.legal_kladr_id, { clearWhenEmpty: true });
                setFieldValue('legal_geo_lat', data.legal_geo_lat, { clearWhenEmpty: true });
                setFieldValue('legal_geo_lon', data.legal_geo_lon, { clearWhenEmpty: true });
                setFieldValue('legal_qc', data.legal_qc, { clearWhenEmpty: true });
                setFieldValue('legal_qc_geo', data.legal_qc_geo, { clearWhenEmpty: true });
                setFieldValue('legal_address_invalid', data.legal_address_invalid, { clearWhenEmpty: true });
                setFieldValue('legal_address_data', data.legal_address_data, { clearWhenEmpty: true });

                lastLoadedQuery = normalizedQuery;
                showStatus(json?.message || 'Данные успешно заполнены.', 'success');
            } catch (error) {
                showStatus(error?.message || 'Не удалось получить данные из DaData.', 'error');
            } finally {
                isLoading = false;
                fillButton.disabled = false;
            }
        };

        fillButton.addEventListener('click', () => {
            loadFromDaData({ silentInvalid: false });
        });

        queryInput.addEventListener('blur', () => {
            loadFromDaData({ silentInvalid: true });
        });

        queryInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            loadFromDaData({ silentInvalid: false });
        });

        queryInput.addEventListener('paste', () => {
            window.setTimeout(() => {
                loadFromDaData({ silentInvalid: true });
            }, 0);
        });

        form.querySelector('input[name="legal_address"]')?.addEventListener('input', () => {
            clearLegalAddressMeta();
        });

        if (!(createSearchInput instanceof HTMLInputElement) || !(createSearchResults instanceof HTMLElement) || !(createSearchStatus instanceof HTMLElement) || !createSuggestUrl) {
            return;
        }

        const showCreateSearchStatus = (message, state = 'info') => {
            createSearchStatus.hidden = !message;
            createSearchStatus.textContent = message;
            createSearchStatus.classList.remove('is-success', 'is-error');

            if (state === 'success') {
                createSearchStatus.classList.add('is-success');
            }

            if (state === 'error') {
                createSearchStatus.classList.add('is-error');
            }
        };

        const hideCreateSearchResults = () => {
            createSearchResults.hidden = true;
            createSearchResults.innerHTML = '';
        };

        const renderCreateSearchResults = (suggestions) => {
            createSearchResults.innerHTML = '';

            if (!Array.isArray(suggestions) || suggestions.length === 0) {
                hideCreateSearchResults();
                return;
            }

            suggestions.forEach((item) => {
                const counterparty = item?.counterparty || {};
                const button = document.createElement('button');
                const title = document.createElement('span');
                const meta = document.createElement('span');

                button.type = 'button';
                button.className = 'cp-search-suggest__item';
                title.className = 'cp-search-suggest__title';
                title.textContent = counterparty.label || counterparty.name || 'Контрагент';

                meta.className = 'cp-search-suggest__meta';
                meta.textContent = `${counterparty.phone || 'Телефон не указан'}${item?.source === 'dadata' ? ' · DaData · заполнить форму' : ' · Уже есть в базе · открыть карточку'}`;

                button.append(title, meta);

                button.addEventListener('click', () => {
                    if (item?.source === 'local' && typeof item?.action_url === 'string' && item.action_url !== '') {
                        window.location.href = item.action_url;
                        return;
                    }

                    applyCounterpartyData(counterparty);

                    if (counterparty.name) {
                        createSearchInput.value = counterparty.name;
                    }

                    hideCreateSearchResults();
                    showCreateSearchStatus('Данные из DaData перенесены в форму.', 'success');
                });

                createSearchResults.appendChild(button);
            });

            createSearchResults.hidden = false;
        };

        const executeCreateSearch = async () => {
            const query = createSearchInput.value.trim();

            if (query.length < 2) {
                hideCreateSearchResults();
                showCreateSearchStatus('');
                return;
            }

            showCreateSearchStatus('Ищу совпадения в базе и через DaData...');

            try {
                const url = new URL(createSuggestUrl, window.location.origin);
                url.searchParams.set('query', query);

                const response = await fetch(url.toString(), {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const json = await response.json();

                if (!response.ok) {
                    throw new Error(json?.message || 'Не удалось выполнить поиск контрагента.');
                }

                renderCreateSearchResults(json?.suggestions || []);

                const hasSuggestions = Array.isArray(json?.suggestions) && json.suggestions.length > 0;
                const emptyMessage = json?.dadata_error
                    ? `DaData недоступен: ${json.dadata_error}`
                    : 'Ничего не найдено.';

                showCreateSearchStatus(
                    hasSuggestions ? 'Выберите вариант для заполнения формы.' : emptyMessage,
                    hasSuggestions ? 'success' : 'error',
                );
            } catch (error) {
                hideCreateSearchResults();
                showCreateSearchStatus(error instanceof Error ? error.message : 'Не удалось выполнить поиск контрагента.', 'error');
            }
        };

        createSearchInput.addEventListener('input', () => {
            window.clearTimeout(createSearchTimer);
            createSearchTimer = window.setTimeout(executeCreateSearch, 300);
        });

        createSearchInput.addEventListener('focus', () => {
            if (createSearchInput.value.trim().length >= 2) {
                executeCreateSearch();
            }
        });

        createSearchInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            executeCreateSearch();
        });

        document.addEventListener('click', (event) => {
            if (!createSearch.contains(event.target)) {
                hideCreateSearchResults();
            }
        });
    })();
</script>
