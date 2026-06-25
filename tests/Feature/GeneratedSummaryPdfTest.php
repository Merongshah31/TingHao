<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GeneratedSummaryPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_generated_summary_pdf_when_inventory_is_empty(): void
    {
        Carbon::setTestNow('2026-06-25 09:30:00');

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.generated-summary.pdf'));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('inventory-summary-2026-06-25-0930.pdf');
    }

    public function test_staff_cannot_download_generated_summary_pdf(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($staff)
            ->get(route('reports.generated-summary.pdf'))
            ->assertForbidden();
    }
}
