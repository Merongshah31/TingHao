<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_staff_can_view_help_center(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);

        foreach ([$admin, $staff] as $user) {
            $this->actingAs($user)
                ->get(route('help-center.index'))
                ->assertOk()
                ->assertSee('FAQ &amp; Guidelines', false)
                ->assertSee('What is Ting Hao Inventory Management System?')
                ->assertSee('Staff Daily Guideline')
                ->assertSee('Purchase Order Guideline')
                ->assertSee('Smart Stock Planner Guideline')
                ->assertSee('id="helpSearch"', false)
                ->assertSee('<details', false);
        }
    }

    public function test_guest_cannot_view_help_center(): void
    {
        $this->get(route('help-center.index'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_links_to_help_center(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('help-center.index'), false)
            ->assertSee('FAQ &amp; Guidelines', false);
    }

    public function test_help_center_renders_mandarin(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($staff)
            ->withSession(['locale' => 'zh_CN'])
            ->get(route('help-center.index'))
            ->assertOk()
            ->assertSee('常见问题与指南')
            ->assertSee('员工每日指南');
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
