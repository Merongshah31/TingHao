@extends('layouts.app')

@section('content')
@php
    $categories = [
        __('messages.getting_started'),
        __('messages.inventory'),
        __('messages.stock_movement'),
        __('messages.low_stock_restock'),
        __('messages.supplier_purchase_order'),
        __('messages.smart_stock_memory_planner'),
        __('messages.reports_pdf'),
        __('messages.backup_system'),
        __('messages.account_language'),
    ];

    $faqs = [
        __('messages.getting_started') => [
            [__('messages.faq_what_is_system_q'), __('messages.faq_what_is_system_a')],
            [__('messages.faq_who_can_use_q'), __('messages.faq_who_can_use_a')],
        ],
        __('messages.inventory') => [
            [__('messages.faq_add_ingredient_q'), __('messages.faq_add_ingredient_a')],
            [__('messages.faq_staff_edit_delete_q'), __('messages.faq_staff_edit_delete_a')],
        ],
        __('messages.stock_movement') => [
            [__('messages.faq_stock_in_q'), __('messages.faq_stock_in_a')],
            [__('messages.faq_stock_out_q'), __('messages.faq_stock_out_a')],
            [__('messages.faq_stock_movement_update_q'), __('messages.faq_stock_movement_update_a')],
        ],
        __('messages.low_stock_restock') => [
            [__('messages.faq_low_stock_meaning_q'), __('messages.faq_low_stock_meaning_a')],
            [__('messages.faq_staff_request_stock_q'), __('messages.faq_staff_request_stock_a')],
            [__('messages.faq_restock_status_q'), __('messages.faq_restock_status_a')],
        ],
        __('messages.supplier_purchase_order') => [
            [__('messages.faq_po_demo_q'), __('messages.faq_po_demo_a')],
            [__('messages.faq_po_demo_email_q'), __('messages.faq_po_demo_email_a')],
            [__('messages.faq_po_demo_inventory_q'), __('messages.faq_po_demo_inventory_a')],
        ],
        __('messages.smart_stock_memory_planner') => [
            [__('messages.faq_smart_planner_q'), __('messages.faq_smart_planner_a')],
            [__('messages.faq_real_ai_q'), __('messages.faq_real_ai_a')],
            [__('messages.faq_save_budget_q'), __('messages.faq_save_budget_a')],
        ],
        __('messages.reports_pdf') => [
            [__('messages.faq_pdf_report_q'), __('messages.faq_pdf_report_a')],
        ],
        __('messages.backup_system') => [
            [__('messages.faq_backup_snapshot_q'), __('messages.faq_backup_snapshot_a')],
            [__('messages.faq_delete_old_backups_q'), __('messages.faq_delete_old_backups_a')],
        ],
        __('messages.account_language') => [
            [__('messages.faq_mandarin_q'), __('messages.faq_mandarin_a')],
        ],
    ];

    $guidelines = [
        [__('messages.staff_daily_guideline'), ['staff_check_dashboard', 'staff_check_low_stock', 'staff_request_stock', 'staff_record_stock_in', 'staff_record_stock_out', 'staff_check_expiry']],
        [__('messages.admin_daily_guideline'), ['admin_review_requests', 'admin_check_movements', 'admin_manage_suppliers', 'admin_generate_reports', 'admin_review_backups', 'admin_use_planner']],
        [__('messages.inventory_guideline'), ['inventory_clear_names', 'inventory_minimum_stock', 'inventory_expiry_date', 'inventory_link_supplier', 'inventory_avoid_delete']],
        [__('messages.stock_movement_guideline'), ['movement_correct_quantity', 'movement_reason_notes', 'movement_no_over_out', 'movement_check_before_after']],
        [__('messages.purchase_order_demo_guideline'), ['po_demo_create', 'po_demo_send', 'po_demo_confirm', 'po_demo_receive', 'po_demo_close', 'po_demo_future_real']],
        [__('messages.smart_stock_planner_guideline'), ['planner_calendar_badges', 'planner_add_stock', 'planner_buy_less', 'planner_do_not_buy', 'planner_expiry_risk', 'planner_festival_prep']],
        [__('messages.backup_guideline'), ['backup_before_update', 'backup_delete_old', 'backup_keep_latest', 'backup_not_full_database']],
    ];
@endphp

<main class="admin-page help-center-page">
    <section class="page-shell">
        <div class="page-heading">
            <div>
                <p class="eyebrow">{{ __('messages.help_center') }}</p>
                <h1>{{ __('messages.faq_guidelines') }}</h1>
                <p>{{ __('messages.quick_guide_subtitle') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
            </div>
        </div>

        <section class="help-search-panel">
            <i data-lucide="search"></i>
            <input id="helpSearch" type="search" placeholder="{{ __('messages.search_faq_guideline') }}" aria-label="{{ __('messages.search_faq_guideline') }}">
        </section>

        <nav class="help-category-tabs" aria-label="{{ __('messages.faq_guidelines') }}">
            @foreach ($categories as $category)
                <a href="#{{ \Illuminate\Support\Str::slug($category) }}">{{ $category }}</a>
            @endforeach
        </nav>

        <section class="help-section">
            <div class="section-heading-row no-padding">
                <h2>{{ __('messages.faq_guidelines') }}</h2>
            </div>

            <div class="faq-groups">
                @foreach ($faqs as $category => $items)
                    <article class="faq-group help-filter-item" id="{{ \Illuminate\Support\Str::slug($category) }}">
                        <p class="eyebrow">{{ $category }}</p>
                        @foreach ($items as [$question, $answer])
                            <details class="faq-card help-filter-item">
                                <summary>{{ $question }}</summary>
                                <p>{{ $answer }}</p>
                            </details>
                        @endforeach
                    </article>
                @endforeach
            </div>
        </section>

        <section class="help-section">
            <div class="section-heading-row no-padding">
                <h2>{{ __('messages.guidelines') }}</h2>
            </div>

            <div class="guideline-grid">
                @foreach ($guidelines as [$title, $keys])
                    <article class="guideline-card help-filter-item">
                        <span><i data-lucide="check-circle-2"></i></span>
                        <h3>{{ $title }}</h3>
                        <ul>
                            @foreach ($keys as $key)
                                <li>{{ __('messages.guideline_'.$key) }}</li>
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        </section>
    </section>
</main>

<script>
    (() => {
        const input = document.getElementById('helpSearch');
        const items = document.querySelectorAll('.help-filter-item');

        input.addEventListener('input', () => {
            const keyword = input.value.trim().toLowerCase();

            items.forEach((item) => {
                item.hidden = keyword !== '' && !item.textContent.toLowerCase().includes(keyword);
            });
        });
    })();
</script>
@endsection
