@php
    $isCreateMode = $mode === 'create';
    $formAction = $isCreateMode ? route('counterparties.store') : route('counterparties.update', $counterparty);
    $contactRows = old('contacts');

    if (! is_array($contactRows)) {
        $contactRows = $counterparty->contacts
            ->map(fn ($contact) => [
                'id' => $contact->id,
                'full_name' => $contact->full_name,
                'position' => $contact->notes,
                'phone_mobile' => $contact->phone_mobile,
                'email' => $contact->email,
                'is_primary' => (bool) $contact->is_primary,
            ])
            ->values()
            ->all();
    }

    if ($contactRows === []) {
        $contactRows[] = [
            'id' => null,
            'full_name' => '',
            'position' => '',
            'phone_mobile' => '',
            'email' => '',
            'is_primary' => true,
        ];
    }

    $primaryIndex = old('primary_contact_index');
    if ($primaryIndex === null) {
        $primaryIndex = collect($contactRows)->search(fn ($row) => ! empty($row['is_primary']));
        $primaryIndex = $primaryIndex === false ? 0 : $primaryIndex;
    }
@endphp

<form id="cp-counterparty-form" class="cp-inline-form cp-contacts-edit" action="{{ $formAction }}" method="post">
    @csrf
    @if (! $isCreateMode)
        @method('PUT')
    @endif

    <input type="hidden" name="active_tab" value="contacts">
    <input data-counterparty-type-input type="hidden" name="type" value="{{ old('type', $counterparty->type) }}">

    <div class="cp-contacts-edit__head">
        <p>{{ count($contactRows) }} контакта</p>
        <button id="cp-add-contact" type="button" class="ad-btn">+ Добавить</button>
    </div>

    <div id="cp-contacts-list" class="cp-contacts-edit__list">
        @foreach ($contactRows as $index => $row)
            <article class="cp-contacts-edit__card" data-contact-card>
                <div class="cp-contacts-edit__card-head">
                    <div class="cp-contacts-edit__title-wrap">
                        <strong>{{ $row['full_name'] ?: 'Новый контакт' }}</strong>
                        @if ((int) $primaryIndex === (int) $index)
                            <span class="cp-contacts-edit__primary">☆ Основной</span>
                        @endif
                    </div>
                    <div class="cp-contacts-edit__tools">
                        <label class="cp-contacts-edit__star {{ (int) $primaryIndex === (int) $index ? 'is-primary' : '' }}" title="Сделать основным">
                            <input type="radio" name="primary_contact_index" value="{{ $index }}" {{ (int) $primaryIndex === (int) $index ? 'checked' : '' }}>
                            <span>☆</span>
                        </label>
                        <button type="button" class="ad-btn" data-remove-contact title="Удалить">×</button>
                    </div>
                </div>

                <input type="hidden" name="contacts[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
                <input type="hidden" name="contacts[{{ $index }}][deleted]" value="0" data-deleted-flag>

                <div class="cp-inline-form__grid">
                    <label class="cp-inline-form__field">
                        <span>ФИО <em>*</em></span>
                        <input type="text" name="contacts[{{ $index }}][full_name]" value="{{ $row['full_name'] ?? '' }}" placeholder="Иванов Иван Иванович">
                    </label>
                    <label class="cp-inline-form__field">
                        <span>Должность</span>
                        <input type="text" name="contacts[{{ $index }}][position]" value="{{ $row['position'] ?? '' }}" placeholder="Директор">
                    </label>
                    <label class="cp-inline-form__field">
                        <span>Телефон</span>
                        <input type="text" name="contacts[{{ $index }}][phone_mobile]" value="{{ $row['phone_mobile'] ?? '' }}" placeholder="+7 (999) 123-45-67">
                    </label>
                    <label class="cp-inline-form__field">
                        <span>Email</span>
                        <input type="email" name="contacts[{{ $index }}][email]" value="{{ $row['email'] ?? '' }}" placeholder="email@example.com">
                    </label>
                </div>
            </article>
        @endforeach
    </div>

    <template id="cp-contact-template">
        <article class="cp-contacts-edit__card" data-contact-card>
            <div class="cp-contacts-edit__card-head">
                <div class="cp-contacts-edit__title-wrap">
                    <strong>Новый контакт</strong>
                </div>
                <div class="cp-contacts-edit__tools">
                    <label class="cp-contacts-edit__star" title="Сделать основным">
                        <input type="radio" name="primary_contact_index" value="__INDEX__">
                        <span>☆</span>
                    </label>
                    <button type="button" class="ad-btn" data-remove-contact title="Удалить">×</button>
                </div>
            </div>

            <input type="hidden" name="contacts[__INDEX__][id]" value="">
            <input type="hidden" name="contacts[__INDEX__][deleted]" value="0" data-deleted-flag>

            <div class="cp-inline-form__grid">
                <label class="cp-inline-form__field">
                    <span>ФИО <em>*</em></span>
                    <input type="text" name="contacts[__INDEX__][full_name]" value="" placeholder="Иванов Иван Иванович">
                </label>
                <label class="cp-inline-form__field">
                    <span>Должность</span>
                    <input type="text" name="contacts[__INDEX__][position]" value="" placeholder="Директор">
                </label>
                <label class="cp-inline-form__field">
                    <span>Телефон</span>
                    <input type="text" name="contacts[__INDEX__][phone_mobile]" value="" placeholder="+7 (999) 123-45-67">
                </label>
                <label class="cp-inline-form__field">
                    <span>Email</span>
                    <input type="email" name="contacts[__INDEX__][email]" value="" placeholder="email@example.com">
                </label>
            </div>
        </article>
    </template>
</form>

<script>
(() => {
    const list = document.getElementById('cp-contacts-list');
    const addBtn = document.getElementById('cp-add-contact');
    const template = document.getElementById('cp-contact-template');
    if (!list || !addBtn || !template) return;

    const nextIndex = () => list.querySelectorAll('[data-contact-card]').length;

    const bindRemoveButtons = () => {
        list.querySelectorAll('[data-remove-contact]').forEach((btn) => {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', () => {
                const card = btn.closest('[data-contact-card]');
                if (!card) return;
                const deletedFlag = card.querySelector('[data-deleted-flag]');
                if (deletedFlag) {
                    deletedFlag.value = '1';
                    card.style.display = 'none';
                } else {
                    card.remove();
                }
            });
        });
    };

    const bindPrimaryStars = () => {
        list.querySelectorAll('.cp-contacts-edit__star input[type="radio"]').forEach((input) => {
            if (input.dataset.boundPrimary === '1') return;
            input.dataset.boundPrimary = '1';

            input.addEventListener('change', () => {
                list.querySelectorAll('.cp-contacts-edit__star').forEach((star) => star.classList.remove('is-primary'));
                const parent = input.closest('.cp-contacts-edit__star');
                if (parent) {
                    parent.classList.add('is-primary');
                }
            });
        });
    };

    addBtn.addEventListener('click', () => {
        const index = nextIndex();
        const html = template.innerHTML.replaceAll('__INDEX__', String(index));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const card = wrapper.firstElementChild;
        if (card) {
            list.appendChild(card);
            bindRemoveButtons();
            bindPrimaryStars();
        }
    });

    bindRemoveButtons();
    bindPrimaryStars();
})();
</script>
