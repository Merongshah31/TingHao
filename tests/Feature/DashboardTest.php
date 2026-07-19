<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\ApprovalRequest;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierEmailDraft;
use App\Models\User;
use App\Services\Stock\StockPredictionInputBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_recent_stock_movements_from_cached_arrays(): void
    {
        Cache::flush();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $ingredient = Ingredient::create([
            'name' => 'Cake Flour',
            'unit' => 'kg',
            'quantity' => 12,
            'minimum_stock' => 5,
            'cost_price' => 4,
            'selling_price' => 6,
        ]);

        StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'type' => StockMovement::TYPE_IN,
            'quantity' => 3,
            'quantity_before' => 9,
            'quantity_after' => 12,
            'reason' => 'Supplier delivery',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Cake Flour')
            ->assertSee('+3.00');
    }

    public function test_staff_dashboard_only_shows_permitted_po_and_email_autopilot_actions(): void
    {
        Cache::flush();

        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
        ]);
        $otherStaff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
        ]);
        $supplier = Supplier::create(['name' => 'Supplier Ali', 'email' => 'ali@example.test']);

        $otherPurchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-OTHER-0001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'order_date' => now()->toDateString(),
            'subtotal' => 10,
            'created_by' => $otherStaff->id,
            'requested_by' => $otherStaff->id,
        ]);
        $otherPurchaseOrder->supplierEmailDrafts()->create([
            'supplier_id' => $supplier->id,
            'subject' => 'Other draft',
            'body' => 'Other body',
            'status' => SupplierEmailDraft::STATUS_DRAFT,
        ]);

        $ownPurchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-OWN-0001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'order_date' => now()->toDateString(),
            'subtotal' => 10,
            'created_by' => $staff->id,
            'requested_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('PO-OWN-0001')
            ->assertSee('Needs email draft')
            ->assertSee('data-lucide="file-chart-column"', false)
            ->assertSee('data-lucide="activity"', false)
            ->assertDontSee('PO-OTHER-0001')
            ->assertDontSee('Other draft')
            ->assertDontSee('Agent Approvals');
    }

    public function test_dashboard_prediction_signals_hide_meaningless_zero_suggestions(): void
    {
        Cache::flush();

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);
        $addStockIngredient = Ingredient::create([
            'name' => 'Instant Yeast',
            'unit' => 'pack',
            'quantity' => 0,
            'minimum_stock' => 5,
            'cost_price' => 2,
            'selling_price' => 3,
        ]);
        $sufficientIngredient = Ingredient::create([
            'name' => 'Unsalted Butter',
            'unit' => 'kg',
            'quantity' => 20,
            'minimum_stock' => 5,
            'cost_price' => 8,
            'selling_price' => 10,
        ]);
        $inputBuilder = app(StockPredictionInputBuilder::class);

        Cache::put($inputBuilder->cacheKey($addStockIngredient), [
            'available' => true,
            'recommended_action' => 'add_stock_now',
            'action_label' => 'Add Stock Now',
            'action_tone' => 'danger',
            'risk_label' => 'High',
            'suggested_quantity' => 0,
        ], now()->addMinutes(30));
        Cache::put($inputBuilder->cacheKey($sufficientIngredient), [
            'available' => true,
            'recommended_action' => 'do_not_buy',
            'action_label' => 'Do Not Buy',
            'action_tone' => 'success',
            'risk_label' => 'Low',
            'suggested_quantity' => 0,
        ], now()->addMinutes(30));

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Suggested: 5.00 pack.')
            ->assertSee('Current stock is sufficient. No purchase suggested.')
            ->assertDontSee('Suggested: 0.00');
    }

    public function test_staff_pending_po_autopilot_card_uses_review_wording(): void
    {
        Cache::flush();

        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
        ]);
        $supplier = Supplier::create(['name' => 'Supplier Ali', 'email' => 'ali@example.test']);
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-STAFF-0002',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
            'order_date' => now()->toDateString(),
            'subtotal' => 10,
            'created_by' => $staff->id,
            'requested_by' => $staff->id,
        ]);
        ApprovalRequest::create([
            'purchase_order_id' => $purchaseOrder->id,
            'action_type' => 'purchase_order_approval',
            'status' => ApprovalRequest::STATUS_PENDING,
            'requested_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('PO-STAFF-0002')
            ->assertSee('Review')
            ->assertDontSee('Approve <i data-lucide="arrow-right"></i>', false)
            ->assertDontSee('Agent Approvals');
    }
}
