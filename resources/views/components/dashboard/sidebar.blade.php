<aside class="saas-sidebar" aria-label="Primary navigation">
    <a class="saas-brand" href="{{ route('dashboard') }}" aria-label="Ting Hao dashboard">
        <span class="saas-brand-mark"><i data-lucide="wheat"></i></span>
        <span><strong>Ting Hao</strong><small>{{ __('messages.inventory_suite') }}</small></span>
    </a>

    <nav class="saas-nav">
        <p>{{ __('messages.workspace') }}</p>
        <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard', 'admin.dashboard', 'staff.dashboard')])>
            <i data-lucide="layout-dashboard"></i><span>{{ __('messages.overview') }}</span>
        </a>
        <a href="{{ route('inventory.index') }}" @class(['active' => request()->routeIs('inventory.*')])>
            <i data-lucide="package-2"></i><span>{{ __('messages.inventory') }}</span>
        </a>
        <a href="{{ route('stock.index') }}" @class(['active' => request()->routeIs('stock.*')])>
            <i data-lucide="arrow-left-right"></i><span>{{ __('messages.stock_movement') }}</span>
        </a>
        <a href="{{ route('stock-memory.demo') }}" @class(['active' => request()->routeIs('stock-memory.*')])>
            <i data-lucide="brain-circuit"></i><span>{{ __('messages.smart_stock_memory_planner') }}</span>
        </a>

        <p>{{ __('messages.alerts') }}</p>
        <a href="{{ route('alerts.low-stock') }}" @class(['active' => request()->routeIs('alerts.*')])>
            <i data-lucide="triangle-alert"></i><span>{{ __('messages.low_stock') }}</span>
            @if (($metrics[1]['value'] ?? 0) > 0)<em>{{ $metrics[1]['value'] }}</em>@endif
        </a>
        <a href="{{ route('expiry.index') }}" @class(['active' => request()->routeIs('expiry.*')])>
            <i data-lucide="calendar-clock"></i><span>{{ __('messages.expiry') }}</span>
        </a>
        <a href="{{ route('suppliers.index') }}" @class(['active' => request()->routeIs('suppliers.*')])>
            <i data-lucide="truck"></i><span>{{ __('messages.suppliers') }}</span>
        </a>
        <a href="{{ route('po-demo.index') }}" @class(['active' => request()->routeIs('po-demo.*')])>
            <i data-lucide="send"></i><span>{{ __('messages.purchase_orders') }}</span>
        </a>
        <a href="{{ route('reports.index') }}" @class(['active' => request()->routeIs('reports.*')])>
            <i data-lucide="chart-no-axes-combined"></i><span>{{ __('messages.reports') }}</span>
        </a>
        <a href="{{ route('help-center.index') }}" @class(['active' => request()->routeIs('help-center.*')])>
            <i data-lucide="circle-help"></i><span>{{ __('messages.faq_guidelines') }}</span>
        </a>

        @if (auth()->user()->isAdmin())
            <p>{{ __('messages.administration') }}</p>
            <a href="{{ route('system.settings') }}" @class(['active' => request()->routeIs('system.settings*')])>
                <i data-lucide="settings-2"></i><span>{{ __('messages.settings') }}</span>
            </a>
            <a href="{{ route('system.backups') }}" @class(['active' => request()->routeIs('system.backups*')])>
                <i data-lucide="database-backup"></i><span>{{ __('messages.backups') }}</span>
            </a>
        @endif
    </nav>

    <div class="saas-sidebar-footer">
        <div class="saas-help-icon"><i data-lucide="circle-help"></i></div>
        <div><strong>{{ __('messages.system_support') }}</strong><span>{{ __('messages.system') }}</span></div>
        <i data-lucide="chevron-right"></i>
    </div>
</aside>
