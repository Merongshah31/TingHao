<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\View\View;

class StockMemoryDemoController extends Controller
{
    public function index(): View
    {
        $today = CarbonImmutable::now()->startOfDay();
        $currentMonth = CarbonImmutable::parse('2026-06-01')->startOfMonth();
        $demoAdvice = $this->demoAdvice();
        $calendarDays = $this->calendarDays($currentMonth, $demoAdvice, $today);
        $selectedAdvice = $demoAdvice['2026-06-25'] ?? collect($demoAdvice)->first();

        return view('stock-memory.demo', [
            'title' => 'Ting Hao | '.__('messages.smart_stock_memory_planner'),
            'currentMonth' => $currentMonth,
            'calendarDays' => $calendarDays,
            'demoAdvice' => $demoAdvice,
            'selectedAdvice' => $selectedAdvice,
            'upcomingAlerts' => $this->upcomingAlerts($today),
            'budgetSuggestions' => $this->budgetSuggestions(),
            'futureFeatures' => $this->futureFeatures(),
            'weekdays' => [
                __('messages.monday_short'),
                __('messages.tuesday_short'),
                __('messages.wednesday_short'),
                __('messages.thursday_short'),
                __('messages.friday_short'),
                __('messages.saturday_short'),
                __('messages.sunday_short'),
            ],
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function demoAdvice(): array
    {
        $advice = [
            '2026-06-25' => [
                'date' => CarbonImmutable::parse('2026-06-25'),
                'status' => 'monitor',
                'tone' => 'monitor',
                'title' => __('messages.normal_stock_day'),
                'mainMessage' => __('messages.calendar_advice_normal_message'),
                'suggestedAction' => __('messages.calendar_action_normal'),
                'budgetAdvice' => __('messages.calendar_budget_normal'),
                'reason' => __('messages.calendar_reason_normal'),
                'items' => [
                    __('messages.calendar_item_packaging_enough'),
                    __('messages.calendar_item_palm_oil_no_buy'),
                ],
            ],
            '2026-06-26' => [
                'date' => CarbonImmutable::parse('2026-06-26'),
                'status' => 'add_stock',
                'tone' => 'add-stock',
                'title' => __('messages.weekend_preparation'),
                'mainMessage' => __('messages.calendar_advice_weekend_message'),
                'suggestedAction' => __('messages.calendar_action_weekend'),
                'budgetAdvice' => __('messages.calendar_budget_weekend'),
                'reason' => __('messages.calendar_reason_weekend'),
                'items' => [
                    __('messages.calendar_item_add_flour'),
                    __('messages.calendar_item_add_brown_sugar'),
                ],
            ],
            '2026-06-28' => [
                'date' => CarbonImmutable::parse('2026-06-28'),
                'status' => 'buy_less',
                'tone' => 'buy-less',
                'title' => __('messages.expiry_risk_detected'),
                'mainMessage' => __('messages.calendar_advice_expiry_message'),
                'suggestedAction' => __('messages.calendar_action_expiry'),
                'budgetAdvice' => __('messages.calendar_budget_expiry'),
                'reason' => __('messages.calendar_reason_expiry'),
                'items' => [
                    __('messages.calendar_item_yeast_three'),
                ],
            ],
            '2026-06-30' => [
                'date' => CarbonImmutable::parse('2026-06-30'),
                'status' => 'do_not_buy',
                'tone' => 'do-not-buy',
                'title' => __('messages.overstock_warning'),
                'mainMessage' => __('messages.calendar_advice_overstock_message'),
                'suggestedAction' => __('messages.use_existing_stock_first'),
                'budgetAdvice' => __('messages.calendar_budget_overstock'),
                'reason' => __('messages.calendar_reason_overstock'),
                'items' => [
                    __('messages.calendar_item_avoid_palm_oil'),
                ],
            ],
            '2026-07-05' => [
                'date' => CarbonImmutable::parse('2026-07-05'),
                'status' => 'festival_prep',
                'tone' => 'festival-prep',
                'title' => __('messages.festival_preparation'),
                'mainMessage' => __('messages.calendar_advice_festival_message'),
                'suggestedAction' => __('messages.calendar_action_festival'),
                'budgetAdvice' => __('messages.calendar_budget_festival'),
                'reason' => __('messages.calendar_reason_festival'),
                'items' => [
                    __('messages.calendar_item_check_flour'),
                    __('messages.calendar_item_check_sugar'),
                    __('messages.calendar_item_check_packaging'),
                ],
            ],
            '2026-07-11' => [
                'date' => CarbonImmutable::parse('2026-07-11'),
                'status' => 'add_stock',
                'tone' => 'add-stock',
                'title' => __('messages.weekend_bakery_prep'),
                'mainMessage' => __('messages.calendar_advice_weekend_bakery_message'),
                'suggestedAction' => __('messages.calendar_action_weekend_bakery'),
                'budgetAdvice' => __('messages.calendar_budget_weekend_bakery'),
                'reason' => __('messages.calendar_reason_weekend_bakery'),
                'items' => [
                    __('messages.calendar_item_add_flour'),
                    __('messages.calendar_item_check_packaging'),
                ],
            ],
            '2026-07-18' => [
                'date' => CarbonImmutable::parse('2026-07-18'),
                'status' => 'monitor',
                'tone' => 'monitor',
                'title' => __('messages.normal_demand'),
                'mainMessage' => __('messages.calendar_advice_normal_demand_message'),
                'suggestedAction' => __('messages.calendar_action_normal'),
                'budgetAdvice' => __('messages.calendar_budget_normal_demand'),
                'reason' => __('messages.calendar_reason_normal_demand'),
                'items' => [
                    __('messages.calendar_item_packaging_enough'),
                ],
            ],
            '2026-07-25' => [
                'date' => CarbonImmutable::parse('2026-07-25'),
                'status' => 'do_not_buy',
                'tone' => 'do-not-buy',
                'title' => __('messages.overstock_check'),
                'mainMessage' => __('messages.calendar_advice_overstock_check_message'),
                'suggestedAction' => __('messages.use_existing_stock_first'),
                'budgetAdvice' => __('messages.calendar_budget_overstock'),
                'reason' => __('messages.calendar_reason_overstock_check'),
                'items' => [
                    __('messages.calendar_item_avoid_palm_oil'),
                ],
            ],
            '2026-08-02' => [
                'date' => CarbonImmutable::parse('2026-08-02'),
                'status' => 'add_stock',
                'tone' => 'add-stock',
                'title' => __('messages.school_holiday_demand'),
                'mainMessage' => __('messages.calendar_advice_school_holiday_message'),
                'suggestedAction' => __('messages.calendar_action_school_holiday'),
                'budgetAdvice' => __('messages.calendar_budget_school_holiday'),
                'reason' => __('messages.calendar_reason_school_holiday'),
                'items' => [
                    __('messages.calendar_item_add_flour'),
                    __('messages.calendar_item_add_brown_sugar'),
                    __('messages.calendar_item_check_packaging'),
                ],
            ],
            '2026-08-09' => [
                'date' => CarbonImmutable::parse('2026-08-09'),
                'status' => 'buy_less',
                'tone' => 'buy-less',
                'title' => __('messages.expiry_risk_detected'),
                'mainMessage' => __('messages.calendar_advice_expiry_message'),
                'suggestedAction' => __('messages.calendar_action_expiry'),
                'budgetAdvice' => __('messages.calendar_budget_expiry'),
                'reason' => __('messages.calendar_reason_expiry'),
                'items' => [
                    __('messages.calendar_item_yeast_three'),
                ],
            ],
            '2026-08-16' => [
                'date' => CarbonImmutable::parse('2026-08-16'),
                'status' => 'festival_prep',
                'tone' => 'festival-prep',
                'title' => __('messages.promotion_preparation'),
                'mainMessage' => __('messages.calendar_advice_promotion_message'),
                'suggestedAction' => __('messages.calendar_action_promotion'),
                'budgetAdvice' => __('messages.calendar_budget_promotion'),
                'reason' => __('messages.calendar_reason_promotion'),
                'items' => [
                    __('messages.calendar_item_check_flour'),
                    __('messages.calendar_item_check_sugar'),
                    __('messages.calendar_item_check_packaging'),
                ],
            ],
            '2026-08-23' => [
                'date' => CarbonImmutable::parse('2026-08-23'),
                'status' => 'monitor',
                'tone' => 'monitor',
                'title' => __('messages.normal_stock_day'),
                'mainMessage' => __('messages.calendar_advice_normal_message'),
                'suggestedAction' => __('messages.calendar_action_normal'),
                'budgetAdvice' => __('messages.calendar_budget_normal'),
                'reason' => __('messages.calendar_reason_normal'),
                'items' => [
                    __('messages.calendar_item_packaging_enough'),
                ],
            ],
        ];

        return collect($advice)
            ->sortBy(fn (array $item): string => $item['date']->toDateString())
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $demoAdvice
     * @return array<int, array<string, mixed>>
     */
    private function calendarDays(CarbonImmutable $currentMonth, array $demoAdvice, CarbonImmutable $today): array
    {
        $start = $currentMonth->startOfWeek();
        $end = $currentMonth->endOfMonth()->endOfWeek();
        $days = [];

        for ($date = $start; $date->lessThanOrEqualTo($end); $date = $date->addDay()) {
            $key = $date->toDateString();

            $days[] = [
                'date' => $date,
                'day' => $date->day,
                'key' => $key,
                'inMonth' => $date->month === $currentMonth->month,
                'isToday' => $date->isSameDay($today),
                'advice' => $demoAdvice[$key] ?? null,
            ];
        }

        return $days;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function upcomingAlerts(CarbonImmutable $today): array
    {
        return [
            [
                'title' => __('messages.weekend_preparation'),
                'meta' => __('messages.starts_in_days', ['count' => 2]),
                'body' => __('messages.alert_weekend_prep'),
                'tone' => 'add-stock',
            ],
            [
                'title' => 'Brown Sugar',
                'meta' => __('messages.add_stock'),
                'body' => __('messages.alert_brown_sugar_low'),
                'tone' => 'add-stock',
            ],
            [
                'title' => 'Palm Oil',
                'meta' => __('messages.do_not_buy'),
                'body' => __('messages.alert_palm_oil_enough'),
                'tone' => 'do-not-buy',
            ],
            [
                'title' => 'Instant Yeast',
                'meta' => $today->addDays(3)->format('d M'),
                'body' => __('messages.alert_yeast_expiry'),
                'tone' => 'buy-less',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function budgetSuggestions(): array
    {
        return [
            __('messages.demo_saving_palm_oil'),
            __('messages.demo_saving_instant_yeast'),
            __('messages.demo_saving_brown_sugar'),
            __('messages.calendar_saving_cake_flour'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function futureFeatures(): array
    {
        return [
            __('messages.future_learn_history'),
            __('messages.future_detect_demand'),
            __('messages.future_calendar'),
            __('messages.future_purchase_quantity'),
            __('messages.future_expiry_warning'),
            __('messages.future_budget_savings'),
            __('messages.future_purchase_report'),
        ];
    }
}
