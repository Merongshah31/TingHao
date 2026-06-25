<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_switch_stores_supported_locale_in_session(): void
    {
        $this->from(route('login'))
            ->get(route('language.switch', 'zh_CN'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('locale', 'zh_CN');
    }

    public function test_language_switch_defaults_invalid_locale_to_english(): void
    {
        $this->from(route('login'))
            ->get(route('language.switch', 'fr'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('locale', 'en');
    }

    public function test_admin_dashboard_renders_mandarin_when_locale_is_selected(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->withSession(['locale' => 'zh_CN'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('管理员仪表板')
            ->assertSee('库存分析', false);
    }
}
