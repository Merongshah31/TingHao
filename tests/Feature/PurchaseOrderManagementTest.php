<?php

namespace Tests\Feature;

use App\Mail\PurchaseOrderMail;
use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\SupplierEmailDraft;
use App\Models\StockAllocation;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PurchaseOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_purchase_order_without_changing_stock(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier(['email' => 'supplier@example.com']);
        $ingredient = $this->ingredient(['quantity' => 4, 'minimum_stock' => 10]);

        $this->actingAs($admin)
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'order_date' => '2026-06-25',
                'expected_delivery_date' => '2026-06-30',
                'notes' => 'Please confirm availability.',
                'items' => [
                    [
                        'ingredient_id' => $ingredient->id,
                        'description' => $ingredient->name,
                        'quantity' => 6,
                        'unit' => 'kg',
                        'unit_price' => 5,
                    ],
                ],
            ])
            ->assertRedirect();

        $purchaseOrder = PurchaseOrder::firstOrFail();

        $this->assertSame('PO-2026-0001', $purchaseOrder->po_number);
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchaseOrder->status);
        $this->assertSame('30.00', $purchaseOrder->subtotal);
        $this->assertSame('4.00', $ingredient->fresh()->quantity);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_admin_can_create_purchase_order_from_low_stock_prefill(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $ingredient = $this->ingredient(['name' => 'Cake Flour', 'quantity' => 4, 'minimum_stock' => 10]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.create-from-low-stock'))
            ->assertOk()
            ->assertSee('Cake Flour')
            ->assertSee('value="'.$ingredient->id.'"', false)
            ->assertSee('value="6"', false);
    }

    public function test_create_purchase_order_page_exposes_advisory_suggestions_without_raw_translation_keys(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->get(route('purchase-orders.create'))
            ->assertOk()
            ->assertSee('Select ingredients, quantities, units, and prices. Suggestions use Stock Planner and previous purchase orders.')
            ->assertSee('Estimated from supplier lead time and previous purchase orders.')
            ->assertSee(route('purchase-orders.suggestions'), false)
            ->assertDontSee('messages.purchase_order_items_hint');
    }

    public function test_admin_receives_cached_quantity_supplier_price_and_historical_delivery_suggestions(): void
    {
        Http::fake();
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient([
            'name' => 'Instant Yeast',
            'unit' => 'pack',
            'quantity' => 0,
            'minimum_stock' => 15,
            'cost_price' => 5,
        ]);
        $historicalOrder = $this->purchaseOrder($admin, $supplier, $ingredient);
        $historicalOrder->update([
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'order_date' => '2026-07-10',
            'received_at' => '2026-07-13 09:00:00',
        ]);
        $historicalOrder->items()->firstOrFail()->update(['unit_price' => 6.80]);
        Cache::put('stock_prediction.ingredient.'.$ingredient->id.'.v1', [
            'available' => true,
            'recommended_action' => 'add_stock_now',
            'suggested_quantity' => 30,
            'reason_codes' => ['below_minimum_stock'],
        ], now()->addMinutes(30));

        $this->actingAs($admin)
            ->getJson(route('purchase-orders.suggestions', [
                'supplier_id' => $supplier->id,
                'ingredient_id' => $ingredient->id,
                'order_date' => '2026-07-18',
            ]))
            ->assertOk()
            ->assertJsonPath('suggested_quantity', 30)
            ->assertJsonPath('unit', 'pack')
            ->assertJsonPath('suggested_unit_price', 6.8)
            ->assertJsonPath('expected_delivery_date', '2026-07-21')
            ->assertJsonPath('lead_time_days', 3)
            ->assertJsonPath('source.quantity', 'stock_planner_prediction')
            ->assertJsonPath('source.price', 'latest_supplier_po')
            ->assertJsonPath('source.delivery', 'supplier_po_history');

        Http::assertNothingSent();
    }

    public function test_purchase_order_suggestions_use_positive_stock_and_price_fallbacks(): void
    {
        Http::fake();
        $admin = $this->user(User::ROLE_ADMIN);
        $selectedSupplier = $this->supplier(['name' => 'Selected Supplier']);
        $otherSupplier = $this->supplier(['name' => 'Previous Supplier', 'email' => 'previous@example.com']);
        $ingredient = $this->ingredient([
            'quantity' => 3,
            'minimum_stock' => 20,
            'cost_price' => 4,
        ]);
        $previousOrder = $this->purchaseOrder($admin, $otherSupplier, $ingredient);
        $previousOrder->items()->firstOrFail()->update(['unit_price' => 8.50]);

        $this->actingAs($admin)
            ->getJson(route('purchase-orders.suggestions', [
                'supplier_id' => $selectedSupplier->id,
                'ingredient_id' => $ingredient->id,
                'order_date' => '2026-07-18',
            ]))
            ->assertOk()
            ->assertJsonPath('suggested_quantity', 37)
            ->assertJsonPath('suggested_unit_price', 8.5)
            ->assertJsonPath('expected_delivery_date', '2026-07-20')
            ->assertJsonPath('source.quantity', 'stock_level_fallback')
            ->assertJsonPath('source.price', 'latest_ingredient_po')
            ->assertJsonPath('source.delivery', 'two_day_fallback');

        $costOnlyIngredient = $this->ingredient([
            'name' => 'Cost Only Ingredient',
            'quantity' => 0,
            'minimum_stock' => 0,
            'cost_price' => 4.25,
        ]);

        $this->actingAs($admin)
            ->getJson(route('purchase-orders.suggestions', [
                'supplier_id' => $selectedSupplier->id,
                'ingredient_id' => $costOnlyIngredient->id,
            ]))
            ->assertOk()
            ->assertJsonPath('suggested_quantity', 1)
            ->assertJsonPath('suggested_unit_price', 4.25)
            ->assertJsonPath('source.price', 'ingredient_cost_price');

        Http::assertNothingSent();
    }

    public function test_staff_cannot_access_purchase_order_suggestions(): void
    {
        $staff = $this->user(User::ROLE_STAFF);

        $this->actingAs($staff)
            ->getJson(route('purchase-orders.suggestions'))
            ->assertForbidden();
    }

    public function test_receive_form_creates_standard_stock_allocation_locations_when_missing(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient(['name' => 'Baking Powder']);
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient, quantity: 10);
        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CONFIRMED, 'confirmed_at' => now()]);

        $this->assertSame(0, StockLocation::count());

        $this->actingAs($admin)
            ->get(route('purchase-orders.receive-form', $purchaseOrder))
            ->assertOk()
            ->assertSee(__('messages.store_room'))
            ->assertSee(__('messages.production_area'))
            ->assertSee(__('messages.front_counter'))
            ->assertSee(__('messages.quarantine_damaged'));

        $this->assertSame(4, StockLocation::count());
        $this->assertTrue(StockLocation::where('name', 'Store Room')->where('is_active', true)->exists());
    }

    public function test_receive_action_is_disabled_until_purchase_order_is_confirmed(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Send or prepare supplier email')
            ->assertDontSee(__('messages.receive_available_after_po_confirmed'))
            ->assertDontSee(route('purchase-orders.receive-form', $purchaseOrder), false);

        $this->actingAs($admin)
            ->from(route('purchase-orders.show', $purchaseOrder))
            ->get(route('purchase-orders.receive-form', $purchaseOrder))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('receive');
    }

    public function test_manual_purchase_order_detail_maps_workflow_and_actions_by_status(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Manual Purchase Order Workflow')
            ->assertSeeInOrder(['Draft', 'Email Sent', 'Supplier Confirmed', 'Received', 'Closed'])
            ->assertSee('Send or prepare supplier email')
            ->assertSee(route('purchase-orders.send-email', $purchaseOrder), false)
            ->assertDontSee(__('messages.mark_supplier_confirmed'))
            ->assertDontSee(route('purchase-orders.receive-form', $purchaseOrder), false)
            ->assertDontSee('Rejected');

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_SENT, 'sent_at' => now()]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Wait for supplier confirmation')
            ->assertSee(__('messages.mark_supplier_confirmed'))
            ->assertSee(route('purchase-orders.confirm', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.send-email', $purchaseOrder), false);

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CONFIRMED, 'confirmed_at' => now()]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Receive goods')
            ->assertSee('Receive Goods')
            ->assertSee(route('purchase-orders.receive-form', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.confirm', $purchaseOrder), false);

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Continue receiving goods')
            ->assertSee('Continue Receiving')
            ->assertSee(route('purchase-orders.receive-form', $purchaseOrder), false);

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_RECEIVED, 'received_at' => now()]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Close purchase order')
            ->assertSee(route('purchase-orders.close', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.receive-form', $purchaseOrder), false);

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CLOSED, 'closed_at' => now()]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Completed')
            ->assertDontSee(route('purchase-orders.close', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.confirm', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.receive-form', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.edit', $purchaseOrder), false);

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_REJECTED]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('No further action')
            ->assertSee('Rejected')
            ->assertDontSee(route('purchase-orders.confirm', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.receive-form', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.edit', $purchaseOrder), false);
    }

    public function test_manual_purchase_order_timeline_uses_email_evidence_and_highlights_close_after_receiving(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);
        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'sent_at' => null,
            'confirmed_at' => now(),
            'received_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Not applicable');

        $html = $response->getContent();

        $this->assertMatchesRegularExpression('~<div class="future">\s*<span>2</span>\s*<strong>Email Sent</strong>~', $html);
        $this->assertMatchesRegularExpression('~<div class="completed">\s*<span>4</span>\s*<strong>Received</strong>~', $html);
        $this->assertMatchesRegularExpression('~<div class="current">\s*<span>5</span>\s*<strong>Closed</strong>~', $html);

        SupplierEmailDraft::create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'subject' => 'Reviewed supplier order',
            'body' => 'This reviewed supplier message was marked as sent.',
            'status' => SupplierEmailDraft::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $markedSentHtml = $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Marked Sent')
            ->getContent();

        $this->assertMatchesRegularExpression('~<div class="completed">\s*<span>2</span>\s*<strong>Marked Sent</strong>~', $markedSentHtml);
    }

    public function test_agent_purchase_order_detail_shows_approval_and_email_workflow_without_qwen_on_load(): void
    {
        Http::fake();
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $agentRun = AgentRun::create([
            'user_id' => $staff->id,
            'input_text' => 'Plan a restock purchase order.',
            'input_type' => 'stock_prediction_restock',
            'status' => AgentRun::STATUS_NEEDS_APPROVAL,
        ]);
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);
        $purchaseOrder->update([
            'agent_run_id' => $agentRun->id,
            'requested_by' => $staff->id,
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
        ]);
        $approval = ApprovalRequest::create([
            'agent_run_id' => $agentRun->id,
            'purchase_order_id' => $purchaseOrder->id,
            'type' => ApprovalRequest::TYPE_PURCHASE_ORDER,
            'status' => ApprovalRequest::STATUS_PENDING,
            'requested_by' => $staff->id,
        ]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Agent Purchase Order Workflow')
            ->assertSee('Pending admin approval')
            ->assertSeeInOrder(['PO Drafted', 'Admin Approved', 'Email Drafted', 'Email Approved', 'Marked Sent'])
            ->assertSee(route('purchase-orders.approve', $purchaseOrder), false)
            ->assertSee(route('purchase-orders.reject', $purchaseOrder), false)
            ->assertDontSee('Rejected by Admin');

        $this->actingAs($staff)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Pending admin approval')
            ->assertDontSee(route('purchase-orders.approve', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.reject', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.generate-email-draft', $purchaseOrder), false);

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_REJECTED]);
        $approval->update(['status' => ApprovalRequest::STATUS_REJECTED, 'reviewed_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Rejected by admin')
            ->assertSee('Rejected by Admin')
            ->assertDontSee('Admin Approved')
            ->assertDontSee(route('purchase-orders.confirm', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.receive-form', $purchaseOrder), false);

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_APPROVED, 'approved_by' => $admin->id]);
        $approval->update(['status' => ApprovalRequest::STATUS_APPROVED, 'reviewed_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Approved by admin')
            ->assertSee('Generate Supplier Email Draft')
            ->assertSee(route('purchase-orders.generate-email-draft', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.confirm', $purchaseOrder), false);

        $emailDraft = SupplierEmailDraft::create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'agent_run_id' => $agentRun->id,
            'subject' => 'Restock request',
            'body' => 'Please review this restock request.',
            'status' => SupplierEmailDraft::STATUS_APPROVED,
        ]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Review Supplier Email Draft')
            ->assertSee('Mark Email as Sent')
            ->assertSee(route('supplier-email-drafts.mark-sent', $emailDraft), false)
            ->assertSee('No real email is sent automatically. Admin controls the final action.');

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_REJECTED]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('No further action')
            ->assertDontSee('Mark Email as Sent')
            ->assertDontSee(route('supplier-email-drafts.mark-sent', $emailDraft), false);

        Http::assertNothingSent();
    }

    public function test_staff_cannot_see_admin_close_action_on_received_purchase_order(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);
        $purchaseOrder->update([
            'requested_by' => $staff->id,
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('purchase-orders.show', $purchaseOrder))
            ->assertOk()
            ->assertSee('Close purchase order')
            ->assertDontSee(route('purchase-orders.close', $purchaseOrder), false);
    }

    public function test_receive_form_status_access_rules(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);

        foreach ([PurchaseOrder::STATUS_CONFIRMED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED] as $status) {
            $purchaseOrder->update(['status' => $status]);

            $this->actingAs($admin)
                ->get(route('purchase-orders.receive-form', $purchaseOrder))
                ->assertOk();
        }

        foreach ([
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_PENDING_APPROVAL,
            PurchaseOrder::STATUS_RECEIVED,
            PurchaseOrder::STATUS_CLOSED,
            PurchaseOrder::STATUS_CANCELLED,
        ] as $status) {
            $purchaseOrder->update(['status' => $status]);

            $this->actingAs($admin)
                ->get(route('purchase-orders.receive-form', $purchaseOrder))
                ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
                ->assertSessionHasErrors('receive');
        }
    }

    public function test_receive_form_defaults_clean_rows_and_keeps_quarantine_neutral(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);
        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
        $this->stockLocations();

        $response = $this->actingAs($admin)
            ->get(route('purchase-orders.receive-form', $purchaseOrder))
            ->assertOk()
            ->assertSee('Accepted / Good')
            ->assertSee(__('messages.receiving_formula_help'))
            ->assertSee(__('messages.record_receiving'));

        $html = $response->getContent();

        $this->assertStringContainsString('value="accepted" selected', $html);
        $this->assertStringContainsString('data-quarantine-card', $html);
        $this->assertStringNotContainsString('allocation-card is-warning', $html);
        $this->assertSame(2, substr_count($html, '<button type="submit"'));
    }

    public function test_draft_purchase_order_cannot_be_confirmed_before_email_step(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);

        $this->actingAs($admin)
            ->post(route('purchase-orders.confirm', $purchaseOrder))
            ->assertStatus(422);

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchaseOrder->fresh()->status);
        $this->assertNull($purchaseOrder->fresh()->confirmed_at);
    }

    public function test_admin_can_send_purchase_order_email_and_status_becomes_sent(): void
    {
        Mail::fake();

        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier(['email' => 'supplier@example.com']);
        $ingredient = $this->ingredient(['quantity' => 4]);
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);

        $this->actingAs($admin)
            ->post(route('purchase-orders.send-email', $purchaseOrder))
            ->assertRedirect()
            ->assertSessionHas('status', __('messages.purchase_order_email_sent'));

        Mail::assertSent(PurchaseOrderMail::class);

        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_SENT, $purchaseOrder->status);
        $this->assertSame('supplier@example.com', $purchaseOrder->email_to);
        $this->assertNotNull($purchaseOrder->sent_at);
        $this->assertSame('4.00', $ingredient->fresh()->quantity);
    }

    public function test_admin_can_confirm_receive_and_close_real_purchase_order(): void
    {
        Mail::fake();

        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier(['email' => 'supplier@example.com']);
        $ingredient = $this->ingredient(['quantity' => 4]);
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);
        $locations = $this->stockLocations();

        $this->actingAs($admin)
            ->post(route('purchase-orders.send-email', $purchaseOrder))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('purchase-orders.confirm', $purchaseOrder))
            ->assertRedirect()
            ->assertSessionHas('status', __('messages.supplier_confirmation_recorded'));

        $this->assertSame(PurchaseOrder::STATUS_CONFIRMED, $purchaseOrder->fresh()->status);
        $this->assertSame('4.00', $ingredient->fresh()->quantity);
        $this->assertSame(0, StockMovement::count());

        $this->actingAs($admin)
            ->post(route('purchase-orders.receive', $purchaseOrder), [
                'items' => [
                    $purchaseOrder->items()->firstOrFail()->id => [
                        'received_quantity' => 3,
                        'accepted_quantity' => 3,
                        'damaged_quantity' => 0,
                        'returned_quantity' => 0,
                        'shortage_quantity' => 0,
                        'quality_status' => 'accepted',
                        'allocations' => [
                            $locations['Store Room']->id => 3,
                        ],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status', __('messages.stock_received_successfully'));

        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $purchaseOrder->fresh()->status);
        $this->assertSame('7.00', $ingredient->fresh()->quantity);
        $this->assertSame('3.00', $purchaseOrder->items()->firstOrFail()->received_quantity);
        $this->assertSame('3.00', $purchaseOrder->items()->firstOrFail()->accepted_quantity);
        $this->assertSame(1, StockMovement::count());
        $this->assertSame(1, StockAllocation::count());

        $this->actingAs($admin)
            ->post(route('purchase-orders.receive', $purchaseOrder), [
                'items' => [
                    $purchaseOrder->items()->firstOrFail()->id => [
                        'received_quantity' => 3,
                        'accepted_quantity' => 1,
                        'damaged_quantity' => 2,
                        'returned_quantity' => 2,
                        'shortage_quantity' => 0,
                        'quality_status' => 'returned',
                        'receiving_notes' => 'Two damaged bags returned.',
                        'allocations' => [
                            $locations['Store Room']->id => 1,
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $purchaseOrder->fresh()->status);
        $this->assertSame('8.00', $ingredient->fresh()->quantity);
        $this->assertSame('6.00', $purchaseOrder->items()->firstOrFail()->received_quantity);
        $this->assertSame('4.00', $purchaseOrder->items()->firstOrFail()->accepted_quantity);
        $this->assertSame('2.00', $purchaseOrder->items()->firstOrFail()->damaged_quantity);
        $this->assertSame('2.00', $purchaseOrder->items()->firstOrFail()->returned_quantity);
        $this->assertSame(2, StockMovement::count());
        $this->assertSame(1, SupplierReturn::count());

        $this->actingAs($admin)
            ->post(route('purchase-orders.close', $purchaseOrder))
            ->assertRedirect()
            ->assertSessionHas('status', __('messages.purchase_order_closed_successfully'));

        $this->assertSame(PurchaseOrder::STATUS_CLOSED, $purchaseOrder->fresh()->status);
        $this->assertNotNull($purchaseOrder->fresh()->closed_at);
    }

    public function test_receiving_shortage_only_adds_accepted_stock_and_flags_discrepancy(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient(['name' => 'Cake Flour', 'quantity' => 4]);
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient, quantity: 10);
        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CONFIRMED, 'confirmed_at' => now()]);
        $locations = $this->stockLocations();
        $item = $purchaseOrder->items()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('purchase-orders.receive', $purchaseOrder), [
                'items' => [
                    $item->id => [
                        'received_quantity' => 10,
                        'accepted_quantity' => 8,
                        'damaged_quantity' => 0,
                        'returned_quantity' => 0,
                        'shortage_quantity' => 2,
                        'quality_status' => 'shortage',
                        'receiving_notes' => 'Two kg missing from delivery.',
                        'allocations' => [
                            $locations['Store Room']->id => 6,
                            $locations['Production Area']->id => 2,
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('12.00', $ingredient->fresh()->quantity);
        $this->assertSame('8.00', StockMovement::firstOrFail()->quantity);
        $this->assertSame('2.00', $item->fresh()->shortage_quantity);
        $this->assertSame(0, SupplierReturn::count());
        $this->assertSame(2, StockAllocation::count());
    }

    public function test_receiving_rejects_mismatched_breakdown(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);
        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CONFIRMED, 'confirmed_at' => now()]);
        $locations = $this->stockLocations();
        $item = $purchaseOrder->items()->firstOrFail();

        $response = $this->actingAs($admin)
            ->from(route('purchase-orders.receive-form', $purchaseOrder))
            ->post(route('purchase-orders.receive', $purchaseOrder), [
                'items' => [
                    $item->id => [
                        'received_quantity' => 6,
                        'accepted_quantity' => 4,
                        'damaged_quantity' => 1,
                        'shortage_quantity' => 0,
                        'allocations' => [
                            $locations['Store Room']->id => 4,
                        ],
                    ],
                ],
            ]);

        $response->assertRedirect(route('purchase-orders.receive-form', $purchaseOrder))
            ->assertSessionHasErrors('items');

        $this->assertSame('2.00', $ingredient->fresh()->quantity);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_supplier_email_is_required_before_sending_purchase_order(): void
    {
        Mail::fake();

        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier(['email' => null]);
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);

        $this->actingAs($admin)
            ->post(route('purchase-orders.send-email', $purchaseOrder))
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        Mail::assertNothingSent();
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchaseOrder->fresh()->status);
    }

    public function test_purchase_order_index_shows_status_specific_next_steps(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);

        $statusExpectations = [
            PurchaseOrder::STATUS_PENDING_APPROVAL => 'Waiting admin approval',
            PurchaseOrder::STATUS_APPROVED => 'Prepare supplier email draft',
            PurchaseOrder::STATUS_SENT => 'Confirm before receiving',
            PurchaseOrder::STATUS_CONFIRMED => 'Receive Goods',
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => 'Continue Receiving',
            PurchaseOrder::STATUS_RECEIVED => 'Ready to close',
            PurchaseOrder::STATUS_CLOSED => 'Completed',
            PurchaseOrder::STATUS_REJECTED => 'No further action',
            PurchaseOrder::STATUS_CANCELLED => 'No further action',
        ];

        foreach ($statusExpectations as $status => $label) {
            $purchaseOrder->update(['status' => $status]);

            $response = $this->actingAs($admin)
                ->get(route('purchase-orders.index'))
                ->assertOk()
                ->assertSee($label)
                ->assertDontSee(__('messages.receive_available_after_po_confirmed'));

            if (in_array($status, PurchaseOrder::RECEIVABLE_STATUSES, true)) {
                $response->assertSee(route('purchase-orders.receive-form', $purchaseOrder), false);
            } else {
                $response->assertDontSee(route('purchase-orders.receive-form', $purchaseOrder), false);
            }
        }
    }

    public function test_purchase_order_index_keeps_prediction_source_and_admin_actions_role_safe(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $agentRun = AgentRun::create([
            'user_id' => $staff->id,
            'input_text' => 'Plan a stock prediction restock.',
            'input_type' => 'stock_prediction_restock',
            'status' => AgentRun::STATUS_NEEDS_APPROVAL,
        ]);
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);
        $purchaseOrder->update([
            'agent_run_id' => $agentRun->id,
            'requested_by' => $staff->id,
            'status' => PurchaseOrder::STATUS_PENDING_APPROVAL,
        ]);

        $this->actingAs($admin)
            ->get(route('purchase-orders.index'))
            ->assertOk()
            ->assertSee('Created from Stock Prediction')
            ->assertSee('Review Approval')
            ->assertSee(route('purchase-orders.edit', $purchaseOrder), false);

        $this->actingAs($staff)
            ->get(route('purchase-orders.index'))
            ->assertOk()
            ->assertSee('Created from Stock Prediction')
            ->assertSee('Waiting admin approval')
            ->assertDontSee('Review Approval')
            ->assertDontSee(route('purchase-orders.edit', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.send-email', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.confirm', $purchaseOrder), false)
            ->assertDontSee(route('purchase-orders.close', $purchaseOrder), false);

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CONFIRMED]);

        $this->actingAs($staff)
            ->get(route('purchase-orders.index'))
            ->assertOk()
            ->assertSee('Receive Goods')
            ->assertSee(route('purchase-orders.receive-form', $purchaseOrder), false);
    }

    public function test_staff_can_view_purchase_orders_but_cannot_manage_them(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);
        $purchaseOrder->update(['requested_by' => $staff->id]);

        $this->actingAs($staff)->get(route('purchase-orders.index'))->assertOk();
        $this->actingAs($staff)->get(route('purchase-orders.show', $purchaseOrder))->assertOk();
        $this->actingAs($staff)->get(route('purchase-orders.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('purchase-orders.store'), [])->assertForbidden();
        $this->actingAs($staff)->get(route('purchase-orders.edit', $purchaseOrder))->assertForbidden();
        $this->actingAs($staff)->put(route('purchase-orders.update', $purchaseOrder), [])->assertForbidden();
        $this->actingAs($staff)->delete(route('purchase-orders.destroy', $purchaseOrder))->assertForbidden();
        $this->actingAs($staff)->post(route('purchase-orders.send-email', $purchaseOrder))->assertForbidden();
        $this->actingAs($staff)->post(route('purchase-orders.confirm', $purchaseOrder))->assertForbidden();
        $this->actingAs($staff)
            ->get(route('purchase-orders.receive-form', $purchaseOrder))
            ->assertRedirect(route('purchase-orders.show', $purchaseOrder))
            ->assertSessionHasErrors('receive');
        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CONFIRMED, 'confirmed_at' => now()]);
        $locations = $this->stockLocations();
        $item = $purchaseOrder->items()->firstOrFail();
        $this->actingAs($staff)->post(route('purchase-orders.receive', $purchaseOrder), [
            'items' => [
                $item->id => [
                    'received_quantity' => 6,
                    'accepted_quantity' => 6,
                    'damaged_quantity' => 0,
                    'returned_quantity' => 0,
                    'shortage_quantity' => 0,
                    'allocations' => [
                        $locations['Store Room']->id => 6,
                    ],
                ],
            ],
        ])->assertRedirect();
        $this->actingAs($staff)->post(route('purchase-orders.close', $purchaseOrder))->assertForbidden();

        $otherStaff = $this->user(User::ROLE_STAFF);
        $this->actingAs($otherStaff)->get(route('purchase-orders.show', $purchaseOrder))->assertForbidden();
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function supplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_merge([
            'name' => 'Ting Hao Supplier',
            'contact_person' => 'Supplier Person',
            'email' => 'supplier@example.com',
            'phone' => '0123456789',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function ingredient(array $overrides = []): Ingredient
    {
        return Ingredient::create(array_merge([
            'name' => 'Brown Sugar',
            'unit' => 'kg',
            'quantity' => 2,
            'minimum_stock' => 10,
            'cost_price' => 5,
            'selling_price' => 8,
        ], $overrides));
    }

    /**
     * @return array<string, StockLocation>
     */
    private function stockLocations(): array
    {
        return collect([
            ['name' => 'Store Room', 'type' => StockLocation::TYPE_STORAGE],
            ['name' => 'Production Area', 'type' => StockLocation::TYPE_PRODUCTION],
            ['name' => 'Front Counter', 'type' => StockLocation::TYPE_FRONT],
            ['name' => 'Quarantine / Damaged', 'type' => StockLocation::TYPE_QUARANTINE],
        ])->mapWithKeys(fn (array $location): array => [
            $location['name'] => StockLocation::updateOrCreate(
                ['name' => $location['name']],
                ['type' => $location['type'], 'is_active' => true]
            ),
        ])->all();
    }

    private function purchaseOrder(User $admin, Supplier $supplier, Ingredient $ingredient, int $quantity = 6): PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-2026-0001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'order_date' => '2026-06-25',
            'expected_delivery_date' => '2026-06-30',
            'subtotal' => $quantity * 5,
            'email_to' => $supplier->email,
            'created_by' => $admin->id,
        ]);

        $purchaseOrder->items()->create([
            'ingredient_id' => $ingredient->id,
            'description' => $ingredient->name,
            'quantity' => $quantity,
            'unit' => 'kg',
            'unit_price' => 5,
            'line_total' => $quantity * 5,
        ]);

        return $purchaseOrder;
    }
}
