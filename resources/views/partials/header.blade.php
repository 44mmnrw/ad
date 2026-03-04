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

    <div class="ad-topbar__brand">        
        <span>Авто Доставка</span>
    </div>

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
            <button class="ad-icon-btn" type="submit" aria-label="Выйти">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M14 7l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M19 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M12 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
            </button>
        </form>
    </div>
</header>
