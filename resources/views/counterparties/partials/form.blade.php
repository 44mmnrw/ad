@php
    $isCreateMode = $mode === 'create';
    $formAction = $isCreateMode ? route('counterparties.store') : route('counterparties.update', $counterparty);
@endphp

<form id="cp-counterparty-form" class="cp-inline-form" action="{{ $formAction }}" method="post">
    @csrf
    @if (! $isCreateMode)
        @method('PUT')
    @endif

    <div class="cp-inline-section">
        <h2>Основная информация</h2>
        <label class="cp-inline-form__field cp-inline-form__field--full" data-type-visible-kinds="legal">
            <span data-type-visible-kinds="legal">Название организации <em>*</em></span>
            <input
                type="text"
                name="short_name"
                value="{{ old('short_name', $counterparty->short_name) }}"
                placeholder='ООО "Название компании"'
                data-required-kinds="legal"
                data-clear-when-hidden="1"
            >
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
</form>

<script>
    (() => {
        const form = document.getElementById('cp-counterparty-form');
        if (!form) {
            return;
        }

        const fillButton = form.querySelector('[data-dadata-fill-btn]');
        const statusNode = form.querySelector('[data-dadata-fill-status]');
        const queryInput = form.querySelector('[data-dadata-query-input]') || form.querySelector('input[name="inn"]');
        const endpoint = '{{ route('counterparties.dadata.autofill') }}';
        const validQueryLengths = [10, 12, 13, 15];
        let isLoading = false;
        let lastLoadedQuery = '';

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

        const setFieldValue = (name, value) => {
            const input = form.querySelector(`[name="${name}"]`);
            if (!input || value === null || value === undefined || value === '') {
                return;
            }

            input.value = value;
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
                setFieldValue('actual_address', data.actual_address);

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
    })();
</script>
