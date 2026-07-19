<?php

namespace Tests\Feature;

use App\Mail\SupplierEmailDraftMail;
use App\Models\AgentRun;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\SupplierEmailDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AutopilotPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_scan_uses_fastapi_dedupes_and_never_calls_qwen(): void
    {
        config([
            'autopilot.po_draft_enabled' => false,
            'autopilot.scan_dedupe_minutes' => 30,
        ]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse(0), 200),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier('Golden Grain Supply');
        $ingredient = $this->ingredient($supplier);

        $this->artisan('tinghao:autopilot-scan')->assertSuccessful();
        $this->artisan('tinghao:autopilot-scan')->assertSuccessful();

        $this->assertDatabaseCount('agent_runs', 1);
        $this->assertDatabaseCount('purchase_orders', 0);
        $run = AgentRun::firstOrFail();
        $predictionTool = $run->toolCalls()->where('tool_name', 'predict_stock_action')->firstOrFail();

        $this->assertSame($admin->id, $run->user_id);
        $this->assertSame('autopilot_inventory_scan', $run->input_type);
        $this->assertGreaterThan(0, (float) data_get($predictionTool->output_payload, 'suggested_quantity'));
        $this->assertSame($ingredient->id, data_get($predictionTool->input_payload, 'ingredient_id'));

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/predict-stock-action'));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'dashscope'));
    }

    public function test_high_confidence_scan_creates_one_pending_draft_with_real_supplier_comparison(): void
    {
        config([
            'autopilot.po_draft_enabled' => true,
            'autopilot.minimum_confidence' => 0.75,
            'autopilot.scan_dedupe_minutes' => 30,
        ]);
        Cache::flush();
        Http::fake([
            'http://127.0.0.1:8001/predict-stock-action' => Http::response($this->predictionResponse(0, 0.92), 200),
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $category = Category::create(['name' => 'Baking Supplies']);
        $assignedSupplier = $this->supplier('Assigned Supplier');
        $historySupplier = $this->supplier('History Supplier');
        $ingredient = $this->ingredient($assignedSupplier, [
            'category_id' => $category->id,
        ]);
        $this->ingredient($historySupplier, [
            'category_id' => $category->id,
            'name' => 'Bread Flour',
            'quantity' => 100,
            'minimum_stock' => 10,
        ]);

        $historicalPo = PurchaseOrder::create([
            'po_number' => 'PO-2026-HISTORY',
            'supplier_id' => $historySupplier->id,
            'status' => PurchaseOrder::STATUS_CLOSED,
            'order_date' => now()->subDays(5)->toDateString(),
            'received_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
            'subtotal' => 180,
            'created_by' => $admin->id,
            'requested_by' => $admin->id,
        ]);
        $historicalPo->items()->create([
            'ingredient_id' => $ingredient->id,
            'description' => $ingredient->name,
            'quantity' => 30,
            'unit' => 'kg',
            'unit_price' => 6,
            'line_total' => 180,
            'received_quantity' => 30,
            'accepted_quantity' => 30,
        ]);

        $this->artisan('tinghao:autopilot-scan')->assertSuccessful();
        $this->artisan('tinghao:autopilot-scan')->assertSuccessful();

        $draft = PurchaseOrder::query()->where('status', PurchaseOrder::STATUS_PENDING_APPROVAL)->firstOrFail();
        $draft->load('agentRun');

        $this->assertSame($historySupplier->id, $draft->supplier_id);
        $this->assertGreaterThan(0, (float) $draft->items()->firstOrFail()->quantity);
        $this->assertCount(2, data_get($draft->agentRun->parsed_intent, 'supplier_comparison.suppliers', []));
        $this->assertSame($historySupplier->id, data_get($draft->agentRun->parsed_intent, 'supplier_comparison.recommended_supplier.id'));
        $this->assertSame(1, PurchaseOrder::query()->where('status', PurchaseOrder::STATUS_PENDING_APPROVAL)->count());
    }

    public function test_real_gmail_delivery_is_admin_only_and_records_safe_audit_evidence(): void
    {
        Mail::fake();
        config([
            'autopilot.real_email_enabled' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'sender@example.com',
            'mail.mailers.smtp.password' => 'test-app-password',
            'mail.from.address' => 'sender@example.com',
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        [$purchaseOrder, $draft] = $this->approvedEmailWorkflow($admin);

        $this->actingAs($staff)
            ->post(route('supplier-email-drafts.send-via-gmail', $draft))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.send-via-gmail', $draft))
            ->assertRedirect()
            ->assertSessionHas('status');

        Mail::assertSent(SupplierEmailDraftMail::class, fn (SupplierEmailDraftMail $mail): bool => $mail->hasTo('orders@example.test'));
        $draft->refresh();
        $this->assertSame(SupplierEmailDraft::STATUS_SENT, $draft->status);
        $this->assertSame(SupplierEmailDraft::DELIVERY_DELIVERED, $draft->delivery_status);
        $this->assertSame('gmail_smtp', $draft->delivery_provider);
        $this->assertSame('accepted_by_mail_transport', data_get($draft->delivery_metadata, 'result'));
        $this->assertArrayNotHasKey('password', $draft->delivery_metadata);
        $this->assertSame(PurchaseOrder::STATUS_SENT, $purchaseOrder->fresh()->status);
        $this->assertDatabaseHas('agent_tool_calls', [
            'agent_run_id' => $purchaseOrder->agent_run_id,
            'tool_name' => 'send_supplier_email_gmail',
            'status' => 'completed',
        ]);
    }

    public function test_admin_can_edit_email_content_and_must_approve_it_again(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        [$purchaseOrder, $draft] = $this->approvedEmailWorkflow($admin);

        $this->actingAs($admin)
            ->put(route('supplier-email-drafts.update', $draft), [
                'subject' => 'Updated supplier order',
                'body' => 'Please review the updated quantities and confirm availability.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $draft->refresh();
        $this->assertSame('Updated supplier order', $draft->subject);
        $this->assertSame(SupplierEmailDraft::STATUS_DRAFT, $draft->status);
        $this->assertNull($draft->approved_by);
        $this->assertNull($draft->approved_at);
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $purchaseOrder->fresh()->status);
        $this->assertDatabaseHas('agent_tool_calls', [
            'agent_run_id' => $purchaseOrder->agent_run_id,
            'tool_name' => 'edit_supplier_email_draft',
        ]);
    }

    public function test_email_draft_edit_and_demo_mark_sent_work_before_delivery_audit_migration_is_deployed(): void
    {
        Schema::table('supplier_email_drafts', function (Blueprint $table): void {
            $table->dropIndex(['delivery_status', 'last_delivery_attempt_at']);
            $table->dropColumn([
                'delivery_status',
                'delivery_provider',
                'delivery_metadata',
                'last_delivery_attempt_at',
            ]);
        });

        $admin = $this->user(User::ROLE_ADMIN);
        [$purchaseOrder, $draft] = $this->approvedEmailWorkflow($admin);

        $this->actingAs($admin)
            ->put(route('supplier-email-drafts.update', $draft), [
                'subject' => 'Legacy database draft update',
                'body' => 'This edit must remain usable until the delivery audit migration is applied.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $draft->refresh();
        $this->assertSame('Legacy database draft update', $draft->subject);
        $this->assertSame(SupplierEmailDraft::STATUS_DRAFT, $draft->status);

        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.approve', $draft))
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.mark-sent', $draft))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(SupplierEmailDraft::STATUS_SENT, $draft->fresh()->status);
        $this->assertSame(PurchaseOrder::STATUS_SENT, $purchaseOrder->fresh()->status);
    }

    public function test_gmail_configuration_failure_is_audited_without_sending_or_losing_approval(): void
    {
        Mail::fake();
        config([
            'autopilot.real_email_enabled' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.from.address' => 'sender@example.com',
        ]);

        $admin = $this->user(User::ROLE_ADMIN);
        [$purchaseOrder, $draft] = $this->approvedEmailWorkflow($admin);

        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.send-via-gmail', $draft))
            ->assertRedirect()
            ->assertSessionHasErrors('supplier_email_draft');

        Mail::assertNothingSent();
        $draft->refresh();
        $this->assertSame(SupplierEmailDraft::STATUS_APPROVED, $draft->status);
        $this->assertSame(SupplierEmailDraft::DELIVERY_FAILED, $draft->delivery_status);
        $this->assertSame('not_delivered', data_get($draft->delivery_metadata, 'result'));
        $this->assertDatabaseHas('agent_tool_calls', [
            'agent_run_id' => $purchaseOrder->agent_run_id,
            'tool_name' => 'send_supplier_email_gmail',
            'status' => 'failed',
        ]);
    }

    public function test_supplier_confirmation_receiving_and_close_complete_verify_audit(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $supplier = $this->supplier('Receiving Supplier');
        $ingredient = $this->ingredient($supplier, ['quantity' => 3]);
        $agentRun = AgentRun::create([
            'user_id' => $admin->id,
            'input_text' => 'Verify the approved restock workflow.',
            'input_type' => 'stock_prediction_restock',
            'status' => AgentRun::STATUS_COMPLETED,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-2026-VERIFY',
            'supplier_id' => $supplier->id,
            'agent_run_id' => $agentRun->id,
            'status' => PurchaseOrder::STATUS_SENT,
            'order_date' => now()->toDateString(),
            'sent_at' => now(),
            'subtotal' => 10,
            'created_by' => $admin->id,
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
        ]);
        $item = $purchaseOrder->items()->create([
            'ingredient_id' => $ingredient->id,
            'description' => $ingredient->name,
            'quantity' => 2,
            'unit' => 'kg',
            'unit_price' => 5,
            'line_total' => 10,
        ]);
        $storeRoom = StockLocation::create([
            'name' => 'Store Room',
            'type' => StockLocation::TYPE_STORAGE,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('purchase-orders.confirm', $purchaseOrder))->assertRedirect();
        $this->actingAs($admin)->post(route('purchase-orders.receive', $purchaseOrder), [
            'items' => [
                $item->id => [
                    'received_quantity' => 2,
                    'accepted_quantity' => 2,
                    'damaged_quantity' => 0,
                    'returned_quantity' => 0,
                    'shortage_quantity' => 0,
                    'quality_status' => 'accepted',
                    'allocations' => [$storeRoom->id => 2],
                ],
            ],
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('purchase-orders.close', $purchaseOrder))->assertRedirect();

        foreach (['record_supplier_confirmation', 'record_goods_receiving', 'close_purchase_order_workflow'] as $toolName) {
            $this->assertDatabaseHas('agent_tool_calls', [
                'agent_run_id' => $agentRun->id,
                'tool_name' => $toolName,
                'status' => 'completed',
            ]);
        }

        $receivingAudit = $agentRun->toolCalls()->where('tool_name', 'record_goods_receiving')->firstOrFail();
        $this->assertSame(2.0, (float) data_get($receivingAudit->output_payload, 'accepted_quantity'));
        $this->assertSame(0.0, (float) data_get($receivingAudit->output_payload, 'damaged_quantity'));
        $this->assertSame(PurchaseOrder::STATUS_CLOSED, $purchaseOrder->fresh()->status);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    private function supplier(string $name): Supplier
    {
        return Supplier::create([
            'name' => $name,
            'contact_person' => 'Procurement Contact',
            'email' => str($name)->slug().'@example.test',
            'phone' => '0123456789',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function ingredient(Supplier $supplier, array $overrides = []): Ingredient
    {
        return Ingredient::create(array_merge([
            'supplier_id' => $supplier->id,
            'name' => 'Sugar',
            'unit' => 'kg',
            'quantity' => 3,
            'minimum_stock' => 20,
            'cost_price' => 5,
            'selling_price' => 8,
        ], $overrides));
    }

    /** @return array{0: PurchaseOrder, 1: SupplierEmailDraft} */
    private function approvedEmailWorkflow(User $admin): array
    {
        $supplier = Supplier::create([
            'name' => 'Email Supplier',
            'contact_person' => 'Sales Team',
            'email' => 'orders@example.test',
            'phone' => '0123456789',
        ]);
        $ingredient = $this->ingredient($supplier);
        $agentRun = AgentRun::create([
            'user_id' => $admin->id,
            'input_text' => 'Prepare supplier email.',
            'input_type' => 'stock_prediction_restock',
            'status' => AgentRun::STATUS_COMPLETED,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-2026-EMAIL',
            'supplier_id' => $supplier->id,
            'agent_run_id' => $agentRun->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'order_date' => now()->toDateString(),
            'subtotal' => 50,
            'created_by' => $admin->id,
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
        ]);
        $purchaseOrder->items()->create([
            'ingredient_id' => $ingredient->id,
            'description' => $ingredient->name,
            'quantity' => 10,
            'unit' => 'kg',
            'unit_price' => 5,
            'line_total' => 50,
        ]);
        $draft = $purchaseOrder->supplierEmailDrafts()->create([
            'supplier_id' => $supplier->id,
            'agent_run_id' => $agentRun->id,
            'subject' => 'Approved restock order',
            'body' => 'Please confirm this approved restock order.',
            'status' => SupplierEmailDraft::STATUS_APPROVED,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        return [$purchaseOrder, $draft];
    }

    /** @return array<string, mixed> */
    private function predictionResponse(float $suggestedQuantity, float $confidence = 0.9): array
    {
        return [
            'ingredient' => 'Sugar',
            'recommended_action' => 'add_stock_now',
            'estimated_days_until_stockout' => 1,
            'suggested_quantity' => $suggestedQuantity,
            'risk_level' => 'high',
            'confidence' => $confidence,
            'reason_codes' => ['below_minimum_stock', 'stockout_soon'],
            'calculation_summary' => [
                'average_daily_usage' => 3.57,
                'current_quantity' => 3,
                'minimum_stock' => 20,
                'pending_po_quantity' => 0,
            ],
        ];
    }
}
