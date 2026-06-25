<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMemoryDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_stock_memory_demo_without_database_changes(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $ingredientsBefore = Ingredient::count();
        $movementsBefore = StockMovement::count();

        $this->actingAs($admin)
            ->get(route('stock-memory.demo'))
            ->assertOk()
            ->assertSee('Smart Stock Memory Planner')
            ->assertSee('Month Plan')
            ->assertSee('Today’s Stock Advice')
            ->assertSee('Upcoming Preparation Alerts')
            ->assertSee('RM 180.00')
            ->assertDontSee('Demo')
            ->assertDontSee('Recommended to Add Stock');

        $this->assertSame($ingredientsBefore, Ingredient::count());
        $this->assertSame($movementsBefore, StockMovement::count());
    }

    public function test_staff_can_view_stock_memory_demo(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($staff)
            ->get(route('stock-memory.demo'))
            ->assertOk()
            ->assertSee('Smart Stock Memory Planner');
    }

    public function test_guest_cannot_view_stock_memory_demo(): void
    {
        $this->get(route('stock-memory.demo'))
            ->assertRedirect(route('login'));
    }

    public function test_stock_memory_demo_renders_mandarin_when_selected(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->withSession(['locale' => 'zh_CN'])
            ->get(route('stock-memory.demo'))
            ->assertOk()
            ->assertSee('智能库存记忆计划')
            ->assertSee('预计节省预算');
    }

    public function test_stock_memory_calendar_contains_interactive_demo_hooks(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-25 00:00:00'));

        try {
            $admin = $this->user(User::ROLE_ADMIN);

            $this->actingAs($admin)
                ->get(route('stock-memory.demo'))
                ->assertOk()
                ->assertSee('data-date="2026-06-25"', false)
                ->assertSee('data-date="2026-06-26"', false)
                ->assertSee('id="advice-date"', false)
            ->assertSee('id="advice-status"', false)
            ->assertSee('id="advice-items"', false)
            ->assertSee('id="prevMonth"', false)
            ->assertSee('id="nextMonth"', false)
            ->assertSee('const adviceByDate', false)
            ->assertSee('Weekend Preparation')
            ->assertSee('2026-07-05')
            ->assertSee('2026-08-16')
            ->assertSee('School Holiday Demand');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
