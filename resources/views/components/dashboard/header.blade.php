<header class="saas-topbar">
    <div class="saas-topbar-title">
        <button class="saas-icon-button saas-menu-button" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
            <i data-lucide="menu"></i>
        </button>
        <div><span>{{ __('messages.workspace') }}</span><strong>{{ $role }} {{ __('messages.dashboard') }}</strong></div>
    </div>

    <div class="saas-topbar-actions">
        <form class="saas-search" method="get" action="{{ route('inventory.index') }}" role="search">
            <i data-lucide="search"></i>
            <input name="search" type="search" aria-label="{{ __('messages.search_inventory') }}" placeholder="{{ __('messages.search_item_or_sku') }}">
            <kbd>Ctrl K</kbd>
        </form>
        <a class="saas-icon-button notification-button" href="{{ route('alerts.low-stock') }}" aria-label="Open low-stock notifications">
            <i data-lucide="bell"></i>
            @if (($metrics[1]['value'] ?? 0) > 0)<span></span>@endif
        </a>
        <div class="saas-profile">
            <span class="saas-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
            <div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->isAdmin() ? __('messages.admin') : __('messages.staff') }}</small></div>
        </div>
        <form action="{{ route('logout') }}" method="post">
            @csrf
            <button class="saas-icon-button" type="submit" aria-label="{{ __('messages.logout') }}"><i data-lucide="log-out"></i></button>
        </form>
    </div>
</header>
