@php
    $isCreateMode = $mode === 'create';
    $formAction = $isCreateMode ? route('counterparties.store') : route('counterparties.update', $counterparty);
@endphp

<form id="cp-counterparty-form" class="cp-inline-form cp-bank-edit" action="{{ $formAction }}" method="post">
    @csrf
    @if (! $isCreateMode)
        @method('PUT')
    @endif

    <input type="hidden" name="active_tab" value="banking">
    <input data-counterparty-type-input type="hidden" name="type" value="{{ old('type', $counterparty->type) }}">

    <section class="cp-bank-edit__header" aria-label="Банковские реквизиты">
        <span class="cp-bank-edit__icon" aria-hidden="true">
            <svg viewBox="0 0 20 20" focusable="false"><use href="/icons/sprite.svg#icon-doc-landmark"></use></svg>
        </span>
        <div>
            <h3>Банковские реквизиты</h3>
            <p>Используются для выставления счетов и оплаты</p>
        </div>
    </section>

    <div class="cp-inline-section">
        <label class="cp-inline-form__field cp-inline-form__field--full cp-inline-form__field--icon">
            <span>Наименование банка</span>
            <input type="text" name="bank_name" value="{{ old('bank_name', $bankName) }}" placeholder="ПАО Сбербанк">
        </label>

        <div class="cp-inline-form__grid">
            <label class="cp-inline-form__field">
                <span>БИК</span>
                <div class="cp-inline-form__input-with-action">
                    <input type="text" name="bik" value="{{ old('bik', $bankBik) }}" placeholder="044525225" data-dadata-bank-query-input>
                    <button type="button" class="ad-btn" data-dadata-bank-fill-btn>DaData</button>
                </div>
                <small class="cp-inline-form__hint">Введите БИК и нажмите DaData (или просто выйдите из поля).</small>
                <p class="cp-dadata-fill__status" data-dadata-bank-fill-status hidden></p>
            </label>
            <label class="cp-inline-form__field" data-type-visible-kinds="legal">
                <span>КПП</span>
                <input type="text" name="kpp" value="{{ old('kpp', $counterparty->kpp) }}" placeholder="770101001" data-clear-when-hidden="1">
            </label>
        </div>

        <label class="cp-inline-form__field cp-inline-form__field--full">
            <span>Расчётный счёт</span>
            <input type="text" name="bank_account" value="{{ old('bank_account', $bankAccount) }}" placeholder="40702810938000012345">
        </label>

        <label class="cp-inline-form__field cp-inline-form__field--full">
            <span>Корреспондентский счёт</span>
            <input type="text" name="correspondent_account" value="{{ old('correspondent_account', $bankCorr) }}" placeholder="30101810400000000225">
        </label>
    </div>

    <div class="cp-bank-edit__notice">
        <svg viewBox="0 0 12 12" focusable="false" aria-hidden="true"><use href="/icons/sprite.svg#icon-doc-checkcheck"></use></svg>
        <span>Реквизиты заполнены и будут сохранены</span>
    </div>
</form>

<script>
    (() => {
        const form = document.getElementById('cp-counterparty-form');
        if (!form) {
            return;
        }

        const fillButton = form.querySelector('[data-dadata-bank-fill-btn]');
        const statusNode = form.querySelector('[data-dadata-bank-fill-status]');
        const queryInput = form.querySelector('[data-dadata-bank-query-input]') || form.querySelector('input[name="bik"]');
        const endpoint = '{{ route('counterparties.dadata.bank.autofill') }}';
        let isLoading = false;
        let lastLoadedBik = '';

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

        const loadBankByBik = async ({ silentInvalid = false } = {}) => {
            const rawBik = String(queryInput.value || '').trim();
            const normalizedBik = rawBik.replace(/\D+/g, '');

            if (normalizedBik.length !== 9) {
                if (!silentInvalid) {
                    showStatus('Введите корректный БИК (9 цифр).', 'error');
                    queryInput.focus();
                }
                return;
            }

            if (isLoading || normalizedBik === lastLoadedBik) {
                return;
            }

            const token = form.querySelector('input[name="_token"]')?.value || '';
            const payload = new URLSearchParams();
            payload.append('_token', token);
            payload.append('query', normalizedBik);

            isLoading = true;
            fillButton.disabled = true;
            showStatus('Загружаем реквизиты банка из DaData...');

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
                    throw new Error(json?.message || 'Не удалось получить реквизиты банка из DaData.');
                }

                const data = json?.data || {};

                setFieldValue('bank_name', data.bank_name);
                setFieldValue('bik', data.bik);
                setFieldValue('correspondent_account', data.correspondent_account);

                lastLoadedBik = normalizedBik;
                showStatus(json?.message || 'Реквизиты банка успешно заполнены.', 'success');
            } catch (error) {
                showStatus(error?.message || 'Не удалось получить реквизиты банка из DaData.', 'error');
            } finally {
                isLoading = false;
                fillButton.disabled = false;
            }
        };

        fillButton.addEventListener('click', () => {
            loadBankByBik({ silentInvalid: false });
        });

        queryInput.addEventListener('blur', () => {
            loadBankByBik({ silentInvalid: true });
        });

        queryInput.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            loadBankByBik({ silentInvalid: false });
        });

        queryInput.addEventListener('paste', () => {
            window.setTimeout(() => {
                loadBankByBik({ silentInvalid: true });
            }, 0);
        });
    })();
</script>
