<aside class="ad-sidebar" aria-label="Боковая навигация">
    @can('dashboard.view')
        <a class="ad-sidebar__link {{ ($activeMenu ?? null) === 'dashboard' ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
            <span class="ad-sidebar__icon" aria-hidden="true">
                <svg viewBox="0 0 20 20" focusable="false">
                    <use href="/icons/sprite.svg#icon-dashboard"></use>
                </svg>
            </span>
            <span>Дашборд</span>
        </a>
    @endcan

    @can('orders.view')
        <a class="ad-sidebar__link {{ ($activeMenu ?? null) === 'orders' ? 'is-active' : '' }}" href="{{ route('orders.index') }}">
            <span class="ad-sidebar__icon" aria-hidden="true">
                <svg viewBox="0 0 20 20" focusable="false">
                    <use href="/icons/sprite.svg#icon-orders"></use>
                </svg>
            </span>
            <span>Все заявки</span>
        </a>
    @endcan

    @can('counterparties.view')
        <a class="ad-sidebar__link {{ ($activeMenu ?? null) === 'counterparties' ? 'is-active' : '' }}" href="{{ route('counterparties.index') }}">
            <span class="ad-sidebar__icon" aria-hidden="true">
                <svg viewBox="0 0 20 20" focusable="false">
                    <use href="/icons/sprite.svg#icon-counterparties"></use>
                </svg>
            </span>
            <span>Контрагенты</span>
        </a>
    @endcan

    @can('settings.integrations.manage')
        <a class="ad-sidebar__link {{ ($activeMenu ?? null) === 'settings' ? 'is-active' : '' }}" href="{{ route('settings.dadata.edit') }}">
            <span class="ad-sidebar__icon" aria-hidden="true">
                <svg viewBox="0 0 20 20" focusable="false">
                    <use href="/icons/sprite.svg#icon-settings"></use>
                </svg>
            </span>
            <span>Настройки</span>
        </a>
    @endcan

    @can('driver.panel.view')
        <a class="ad-sidebar__link {{ ($activeMenu ?? null) === 'driver-panel' ? 'is-active' : '' }}" href="{{ route('driver.panel') }}" target="_blank">
            <span class="ad-sidebar__icon" aria-hidden="true">
                <svg viewBox="0 0 20 20" focusable="false">
                    <use href="/icons/sprite.svg#icon-stat-drivers"></use>
                </svg>
            </span>
            <span>Панель водителя</span>
        </a>
    @endcan
</aside>
