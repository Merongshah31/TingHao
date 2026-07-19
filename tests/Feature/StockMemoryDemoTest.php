<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMemoryDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_stock_memory_route_redirects_to_stock_planner_calendar_view(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get(route('stock-memory.demo'))
            ->assertRedirect(route('stock-planner.index', ['view' => 'calendar']));
    }

    public function test_old_calendar_demo_route_redirects_to_stock_planner_calendar_view(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($staff)
            ->get(route('stock-planner.calendar-redirect'))
            ->assertRedirect(route('stock-planner.index', ['view' => 'calendar']));
    }

    public function test_guest_cannot_access_old_calendar_redirects(): void
    {
        $this->get(route('stock-memory.demo'))
            ->assertRedirect(route('login'));
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
