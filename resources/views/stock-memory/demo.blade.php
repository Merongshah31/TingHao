@extends('layouts.app')

@section('content')
@php
    $calendarAdvice = collect($demoAdvice)->mapWithKeys(function (array $advice, string $date): array {
        return [
            $date => [
                'date' => $date,
                'dateLabel' => $advice['date']->format('d M Y'),
                'status' => $advice['status'],
                'statusLabel' => __('messages.'.$advice['status']),
                'tone' => $advice['tone'],
                'title' => $advice['title'],
                'message' => $advice['mainMessage'],
                'suggestedAction' => $advice['suggestedAction'],
                'budgetAdvice' => $advice['budgetAdvice'],
                'reason' => $advice['reason'],
                'items' => $advice['items'],
            ],
        ];
    })->all();

    $defaultAdvice = [
        'status' => 'monitor',
        'statusLabel' => __('messages.monitor'),
        'tone' => 'monitor',
        'title' => __('messages.no_special_stock_action'),
        'message' => __('messages.no_special_stock_action_message'),
        'suggestedAction' => __('messages.continue_normal_stock_monitoring'),
        'budgetAdvice' => __('messages.no_purchase_needed_unless_low'),
        'reason' => __('messages.default_calendar_reason'),
        'items' => [],
    ];
@endphp

<main class="admin-page stock-memory-page">
    <section class="page-shell">
        <div class="page-heading smart-memory-hero calendar-hero">
            <div>
                <p class="eyebrow">{{ __('messages.stock_planning_calendar') }}</p>
                <h1>{{ __('messages.smart_stock_memory_planner') }}</h1>
                <p>{{ __('messages.calendar_stock_demo') }}</p>
            </div>
            <div class="page-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-muted">{{ __('messages.dashboard') }}</a>
                <a href="{{ route('inventory.index') }}" class="btn btn-muted">{{ __('messages.view_inventory') }}</a>
                <a href="{{ route('reports.index') }}" class="btn btn-primary">{{ __('messages.view_reports') }}</a>
            </div>
        </div>

        <section class="stock-calendar-layout">
            <article class="stock-calendar-card">
                <div class="stock-calendar-head">
                    <div>
                        <p class="eyebrow">{{ __('messages.month_plan') }}</p>
                        <h2 id="calendar-month-title">{{ $currentMonth->translatedFormat('F Y') }}</h2>
                    </div>
                    <div class="calendar-controls" aria-label="{{ __('messages.month_plan') }}">
                        <button type="button" id="prevMonth" class="month-nav-button">
                            <span aria-hidden="true">←</span>
                            {{ __('messages.previous_month') }}
                        </button>
                        <button type="button" id="nextMonth" class="month-nav-button">
                            {{ __('messages.next_month') }}
                            <span aria-hidden="true">→</span>
                        </button>
                    </div>
                </div>
                <p class="stock-calendar-note">{{ __('messages.stock_memory_calendar_note') }}</p>

                <div class="stock-calendar-weekdays">
                    @foreach ($weekdays as $weekday)
                        <span>{{ $weekday }}</span>
                    @endforeach
                </div>

                <div id="stock-calendar-grid" class="stock-calendar-grid">
                    @foreach ($calendarDays as $day)
                        @php($advice = $day['advice'])
                        <div @class([
                            'stock-calendar-day',
                            'muted' => ! $day['inMonth'],
                            'today' => $day['isToday'],
                            'is-selected' => $day['date']->isSameDay($selectedAdvice['date']),
                            'has-advice' => $advice,
                            $advice ? 'tone-'.$advice['tone'] : '' => $advice,
                        ])
                            data-date="{{ $day['key'] }}"
                            role="button"
                            tabindex="0"
                            aria-pressed="{{ $day['date']->isSameDay($selectedAdvice['date']) ? 'true' : 'false' }}"
                        >
                            <div class="day-number">{{ $day['day'] }}</div>
                            @if ($advice)
                                <span class="calendar-badge badge-{{ $advice['tone'] }}">{{ __('messages.'.$advice['status']) }}</span>
                                <strong>{{ $advice['title'] }}</strong>
                            @endif
                        </div>
                    @endforeach
                </div>
            </article>

            <aside class="stock-advice-panel">
                <p class="eyebrow">{{ __('messages.todays_stock_advice') }}</p>
                <h2>{{ __('messages.stock_advice_for') }} <span id="advice-date">{{ $selectedAdvice['date']->format('d M Y') }}</span></h2>
                <span id="advice-status" class="calendar-badge badge-{{ $selectedAdvice['tone'] }}">{{ __('messages.'.$selectedAdvice['status']) }}</span>
                <h3 id="advice-title">{{ $selectedAdvice['title'] }}</h3>
                <p id="advice-message">{{ $selectedAdvice['mainMessage'] }}</p>

                <dl>
                    <div>
                        <dt>{{ __('messages.suggested_action') }}</dt>
                        <dd id="advice-suggested-action">{{ $selectedAdvice['suggestedAction'] }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('messages.budget_advice') }}</dt>
                        <dd id="advice-budget-advice">{{ $selectedAdvice['budgetAdvice'] }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('messages.reason') }}</dt>
                        <dd id="advice-reason">{{ $selectedAdvice['reason'] }}</dd>
                    </div>
                </dl>

                <ul id="advice-items">
                    @foreach ($selectedAdvice['items'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </aside>
        </section>

        <section class="stock-memory-note">
            <span><i data-lucide="brain-circuit"></i></span>
            <p>{{ __('messages.stock_memory_real_note') }}</p>
        </section>

        <section class="stock-planner-bottom">
            <article class="planner-list-card">
                <div class="panel-title-row">
                    <div>
                        <p class="eyebrow">{{ __('messages.upcoming_preparation_alerts') }}</p>
                        <h2>{{ __('messages.next_stock_actions') }}</h2>
                    </div>
                </div>
                <div class="planner-alert-list">
                    @foreach ($upcomingAlerts as $alert)
                        <div class="planner-alert tone-{{ $alert['tone'] }}">
                            <span>{{ $alert['meta'] }}</span>
                            <strong>{{ $alert['title'] }}</strong>
                            <p>{{ $alert['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="planner-list-card">
                <div class="panel-title-row">
                    <div>
                        <p class="eyebrow">{{ __('messages.budget_saving_advice') }}</p>
                        <h2>{{ __('messages.estimated_budget_saved') }}</h2>
                    </div>
                    <strong class="budget-total">RM 180.00</strong>
                </div>
                <ul class="planner-check-list">
                    @foreach ($budgetSuggestions as $suggestion)
                        <li>{{ $suggestion }}</li>
                    @endforeach
                </ul>
            </article>
        </section>

    </section>
</main>

<script>
    (() => {
        const adviceByDate = @json($calendarAdvice);
        const defaultAdvice = @json($defaultAdvice);
        const weekdays = @json($weekdays);
        const todayDate = @json(now()->toDateString());
        let currentCalendarDate = new Date(@json($currentMonth->format('Y-m-01')).replace(/-/g, '/'));
        const dateFormatter = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
        const monthFormatter = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
            month: 'long',
            year: 'numeric',
        });
        const calendarGrid = document.getElementById('stock-calendar-grid');
        const calendarMonthTitle = document.getElementById('calendar-month-title');
        const prevMonthButton = document.getElementById('prevMonth');
        const nextMonthButton = document.getElementById('nextMonth');
        const adviceDate = document.getElementById('advice-date');
        const adviceStatus = document.getElementById('advice-status');
        const adviceTitle = document.getElementById('advice-title');
        const adviceMessage = document.getElementById('advice-message');
        const adviceSuggestedAction = document.getElementById('advice-suggested-action');
        const adviceBudgetAdvice = document.getElementById('advice-budget-advice');
        const adviceReason = document.getElementById('advice-reason');
        const adviceItems = document.getElementById('advice-items');

        const formatDateLabel = (date) => {
            const parsedDate = new Date(`${date}T00:00:00`);

            if (Number.isNaN(parsedDate.getTime())) {
                return date;
            }

            return dateFormatter.format(parsedDate);
        };

        const toDateString = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        };

        const addDays = (date, days) => {
            const nextDate = new Date(date);
            nextDate.setDate(nextDate.getDate() + days);

            return nextDate;
        };

        const getMondayWeekStart = (date) => {
            const start = new Date(date);
            const day = start.getDay();
            const diff = day === 0 ? -6 : 1 - day;
            start.setDate(start.getDate() + diff);

            return start;
        };

        const getSundayWeekEnd = (date) => {
            const end = new Date(date);
            const day = end.getDay();
            const diff = day === 0 ? 0 : 7 - day;
            end.setDate(end.getDate() + diff);

            return end;
        };

        const getAdviceDatesForMonth = (year, month) => Object.keys(adviceByDate)
            .filter((date) => {
                const adviceDate = new Date(`${date}T00:00:00`);

                return adviceDate.getFullYear() === year && adviceDate.getMonth() === month;
            })
            .sort();

        const setSelectedDay = (selectedDay) => {
            document.querySelectorAll('.stock-calendar-day[data-date]').forEach((day) => {
                const isSelected = day === selectedDay;
                day.classList.toggle('is-selected', isSelected);
                day.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            });
        };

        const renderItems = (items) => {
            adviceItems.innerHTML = '';

            items.forEach((item) => {
                const listItem = document.createElement('li');
                listItem.textContent = item;
                adviceItems.appendChild(listItem);
            });
        };

        const updateAdvicePanel = (date) => {
            const advice = adviceByDate[date] || {
                ...defaultAdvice,
                date,
                dateLabel: formatDateLabel(date),
            };

            adviceDate.textContent = advice.dateLabel || formatDateLabel(date);
            adviceStatus.textContent = advice.statusLabel;
            adviceStatus.className = `calendar-badge badge-${advice.tone}`;
            adviceTitle.textContent = advice.title;
            adviceMessage.textContent = advice.message;
            adviceSuggestedAction.textContent = advice.suggestedAction;
            adviceBudgetAdvice.textContent = advice.budgetAdvice;
            adviceReason.textContent = advice.reason;
            renderItems(advice.items || []);
        };

        const selectDate = (date) => {
            const selectedDay = calendarGrid.querySelector(`[data-date="${date}"]`);

            if (selectedDay) {
                setSelectedDay(selectedDay);
            }

            updateAdvicePanel(date);
        };

        const bindCalendarDay = (day) => {
            day.addEventListener('click', () => {
                selectDate(day.dataset.date);
            });

            day.addEventListener('keydown', (event) => {
                if (!['Enter', ' '].includes(event.key)) {
                    return;
                }

                event.preventDefault();
                day.click();
            });
        };

        const createCalendarDay = (date, activeMonth) => {
            const dateString = toDateString(date);
            const advice = adviceByDate[dateString];
            const day = document.createElement('div');
            day.className = 'stock-calendar-day';
            day.dataset.date = dateString;
            day.setAttribute('role', 'button');
            day.setAttribute('tabindex', '0');
            day.setAttribute('aria-pressed', 'false');

            if (date.getMonth() !== activeMonth) {
                day.classList.add('muted');
            }

            if (dateString === todayDate) {
                day.classList.add('today');
            }

            if (advice) {
                day.classList.add('has-advice', `tone-${advice.tone}`);
            }

            const dayNumber = document.createElement('div');
            dayNumber.className = 'day-number';
            dayNumber.textContent = date.getDate();
            day.appendChild(dayNumber);

            if (advice) {
                const badge = document.createElement('span');
                badge.className = `calendar-badge badge-${advice.tone}`;
                badge.textContent = advice.statusLabel;
                day.appendChild(badge);

                const title = document.createElement('strong');
                title.textContent = advice.title;
                day.appendChild(title);
            }

            bindCalendarDay(day);

            return day;
        };

        const renderCalendar = () => {
            const year = currentCalendarDate.getFullYear();
            const month = currentCalendarDate.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const gridStart = getMondayWeekStart(firstDay);
            const gridEnd = getSundayWeekEnd(lastDay);
            const adviceDates = getAdviceDatesForMonth(year, month);
            const today = new Date(`${todayDate}T00:00:00`);
            const todayIsVisible = today.getFullYear() === year && today.getMonth() === month;
            const selectedDate = adviceDates[0] || (todayIsVisible ? todayDate : toDateString(firstDay));

            calendarMonthTitle.textContent = monthFormatter.format(firstDay);
            calendarGrid.innerHTML = '';

            for (let date = new Date(gridStart); date <= gridEnd; date = addDays(date, 1)) {
                calendarGrid.appendChild(createCalendarDay(date, month));
            }

            selectDate(selectedDate);
        };

        prevMonthButton.addEventListener('click', () => {
            currentCalendarDate = new Date(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth() - 1, 1);
            renderCalendar();
        });

        nextMonthButton.addEventListener('click', () => {
            currentCalendarDate = new Date(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth() + 1, 1);
            renderCalendar();
        });

        document.querySelectorAll('.stock-calendar-day[data-date]').forEach(bindCalendarDay);
    })();
</script>
@endsection
