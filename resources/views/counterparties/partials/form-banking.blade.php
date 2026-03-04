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
            <svg viewBox="0 0 20 20" focusable="false">
                <path d="M10 2.5 3 6h14L10 2.5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                <path d="M4.5 7.2h11M5.5 8.5v6M8.5 8.5v6M11.5 8.5v6M14.5 8.5v6M4 15.8h12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
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
                <input type="text" name="bik" value="{{ old('bik', $bankBik) }}" placeholder="044525225">
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
        <svg viewBox="0 0 20 20" focusable="false" aria-hidden="true">
            <path d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.2 7.2a1 1 0 0 1-1.4 0L4.8 10.6a1 1 0 1 1 1.4-1.4l2.6 2.6 6.5-6.5a1 1 0 0 1 1.4 0Z" fill="currentColor"/>
        </svg>
        <span>Реквизиты заполнены и будут сохранены</span>
    </div>
</form>
