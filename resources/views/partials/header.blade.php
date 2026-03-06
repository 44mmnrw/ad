<header class="ad-topbar">
    @php
        $user = auth()->user();
        $displayName = $user?->name ?: 'Пользователь';
        $nameParts = preg_split('/\s+/u', trim($displayName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = collect($nameParts)
            ->take(2)
            ->map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
        $initials = $initials !== '' ? $initials : 'П';
    @endphp

    <a class="ad-topbar__brand" href="{{ route('dashboard') }}" aria-label="Вернуться на дашборд">
        <span class="ad-topbar__brand-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" focusable="false">
                <use href="/icons/sprite.svg#icon-truck"></use>
            </svg>
        </span>
        <span>Авто Доставка</span>
    </a>

    <nav class="ad-topbar__nav" aria-label="Основная навигация">
        @can('orders.view')
            <a href="{{ route('orders.index') }}">Заявки</a>
        @endcan
        @can('counterparties.view')
            <a href="{{ route('counterparties.index') }}">Контрагенты</a>
        @endcan
        @can('settings.integrations.manage')
            <a href="{{ route('settings.dadata.edit') }}">Интеграции</a>
        @endcan
    </nav>

    <div class="ad-topbar__profile">
        <div class="ad-avatar" aria-hidden="true">{{ $initials }}</div>
        <span>{{ $displayName }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="ad-btn" type="submit" aria-label="Выйти">
                <svg width="16" height="16" viewBox="0 0 16 16" focusable="false" aria-hidden="true">
                    <use href="/icons/sprite.svg#icon-doc-logout"></use>
                </svg>
            </button>
        </form>
    </div>
</header>
