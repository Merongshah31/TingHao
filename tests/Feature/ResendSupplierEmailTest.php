<?php

namespace Tests\Feature;

use App\Models\AgentRun;
use App\Models\AgentToolCall;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierEmailDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Resend\Email;
use Resend\Laravel\Facades\Resend;
use RuntimeException;
use Tests\TestCase;

class ResendSupplierEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_send_supplier_email_draft_through_resend(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        [, $draft] = $this->approvedEmailWorkflow($admin);

        $this->actingAs($staff)
            ->post(route('supplier-email-drafts.send-resend', $draft))
            ->assertForbidden();

        $this->assertSame(SupplierEmailDraft::STATUS_APPROVED, $draft->fresh()->status);
    }

    public function test_resend_blocks_unapproved_po_unapproved_draft_and_disabled_real_email(): void
    {
        config($this->resendConfig(['autopilot.real_email_enabled' => true]));
        $admin = $this->user(User::ROLE_ADMIN);
        [$purchaseOrder, $draft] = $this->approvedEmailWorkflow($admin);

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_DRAFT]);
        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.send-resend', $draft))
            ->assertRedirect()
            ->assertSessionHasErrors('supplier_email_draft');
        $this->assertSame(SupplierEmailDraft::STATUS_APPROVED, $draft->fresh()->status);

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_APPROVED]);
        $draft->update(['status' => SupplierEmailDraft::STATUS_DRAFT]);
        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.send-resend', $draft))
            ->assertRedirect()
            ->assertSessionHasErrors('supplier_email_draft');
        $this->assertSame(SupplierEmailDraft::STATUS_DRAFT, $draft->fresh()->status);

        $draft->update(['status' => SupplierEmailDraft::STATUS_APPROVED]);
        config(['autopilot.real_email_enabled' => false]);
        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.send-resend', $draft))
            ->assertRedirect()
            ->assertSessionHasErrors('supplier_email_draft');
        $this->assertSame(SupplierEmailDraft::STATUS_APPROVED, $draft->fresh()->status);
    }

    public function test_resend_test_mode_overrides_supplier_email_with_configured_test_recipient(): void
    {
        config($this->resendConfig());
        $resendEmails = $this->fakeResendEmails('re_test_override');
        Resend::shouldReceive('emails')->once()->andReturn($resendEmails);
        $admin = $this->user(User::ROLE_ADMIN);
        [, $draft] = $this->approvedEmailWorkflow($admin, supplierEmail: 'orders@example.test');

        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.send-resend', $draft))
            ->assertRedirect()
            ->assertSessionHas('status');

        $draft->refresh();
        $this->assertSame(SupplierEmailDraft::STATUS_SENT, $draft->status);
        $this->assertSame(['bakerytinghao@outlook.com'], data_get($resendEmails->sent, '0.parameters.to'));
        $this->assertSame('orders@example.test', $draft->purchaseOrder->fresh()->email_to);
        $this->assertSame('or***@example.test', data_get($draft->delivery_metadata, 'intended_recipient'));
        $this->assertSame('ba***@outlook.com', data_get($draft->delivery_metadata, 'recipient'));
    }

    public function test_resend_test_mode_never_sends_to_supplier_email(): void
    {
        config($this->resendConfig());
        $resendEmails = $this->fakeResendEmails('re_test_safety');
        Resend::shouldReceive('emails')->once()->andReturn($resendEmails);
        $admin = $this->user(User::ROLE_ADMIN);
        [, $draft] = $this->approvedEmailWorkflow($admin, supplierEmail: 'supplier-real@example.test');

        $this->actingAs($admin)->post(route('supplier-email-drafts.send-resend', $draft));

        $this->assertNotSame(['supplier-real@example.test'], data_get($resendEmails->sent, '0.parameters.to'));
        $this->assertSame(['bakerytinghao@outlook.com'], data_get($resendEmails->sent, '0.parameters.to'));
    }

    public function test_missing_or_invalid_test_recipient_is_blocked(): void
    {
        config($this->resendConfig(['autopilot.resend_test_recipient' => 'not-an-email']));
        $admin = $this->user(User::ROLE_ADMIN);
        [, $draft] = $this->approvedEmailWorkflow($admin, supplierEmail: 'supplier-real@example.test');

        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.send-resend', $draft))
            ->assertRedirect()
            ->assertSessionHasErrors('supplier_email_draft');

        $this->assertSame(SupplierEmailDraft::STATUS_APPROVED, $draft->fresh()->status);
    }

    public function test_production_mode_uses_supplier_email(): void
    {
        config($this->resendConfig([
            'autopilot.resend_test_mode' => false,
            'autopilot.resend_from_address' => 'verified@tinghao.example',
        ]));
        $resendEmails = $this->fakeResendEmails('re_production');
        Resend::shouldReceive('emails')->once()->andReturn($resendEmails);
        $admin = $this->user(User::ROLE_ADMIN);
        [, $draft] = $this->approvedEmailWorkflow($admin, supplierEmail: 'supplier-real@example.test');

        $this->actingAs($admin)->post(route('supplier-email-drafts.send-resend', $draft));

        $this->assertSame(['supplier-real@example.test'], data_get($resendEmails->sent, '0.parameters.to'));
        $this->assertSame('supplier-real@example.test', $draft->purchaseOrder->fresh()->email_to);
    }

    public function test_intended_and_actual_recipients_are_audited_safely(): void
    {
        config($this->resendConfig());
        $resendEmails = $this->fakeResendEmails('re_audit');
        Resend::shouldReceive('emails')->once()->andReturn($resendEmails);
        $admin = $this->user(User::ROLE_ADMIN);
        [$purchaseOrder, $draft] = $this->approvedEmailWorkflow($admin, supplierEmail: 'supplier-real@example.test');

        $this->actingAs($admin)->post(route('supplier-email-drafts.send-resend', $draft));

        $audit = AgentToolCall::query()
            ->where('agent_run_id', $purchaseOrder->agent_run_id)
            ->where('tool_name', 'send_supplier_email_resend')
            ->firstOrFail();
        $this->assertSame('su***@example.test', data_get($audit->input_payload, 'intended_recipient'));
        $this->assertSame('ba***@outlook.com', data_get($audit->input_payload, 'actual_recipient'));
        $this->assertSame('su***@example.test', data_get($audit->output_payload, 'intended_recipient'));
        $this->assertSame('ba***@outlook.com', data_get($audit->output_payload, 'actual_recipient'));
        $this->assertStringNotContainsString('supplier-real@example.test', json_encode($audit->input_payload));
        $this->assertStringNotContainsString('supplier-real@example.test', json_encode($audit->output_payload));
    }

    public function test_admin_approved_test_draft_sends_through_resend_and_stores_safe_audit(): void
    {
        config($this->resendConfig());
        $resendEmails = $this->fakeResendEmails('re_test_123');
        Resend::shouldReceive('emails')->once()->andReturn($resendEmails);

        $admin = $this->user(User::ROLE_ADMIN);
        [$purchaseOrder, $draft] = $this->approvedEmailWorkflow($admin, supplierEmail: 'orders@example.test');

        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.send-resend', $draft))
            ->assertRedirect()
            ->assertSessionHas('status');

        $draft->refresh();
        $this->assertSame(SupplierEmailDraft::STATUS_SENT, $draft->status);
        $this->assertSame('resend', $draft->provider);
        $this->assertSame('re_test_123', $draft->provider_message_id);
        $this->assertSame(SupplierEmailDraft::DELIVERY_ACCEPTED, $draft->delivery_status);
        $this->assertSame($admin->id, $draft->sent_by);
        $this->assertSame(PurchaseOrder::STATUS_SENT, $purchaseOrder->fresh()->status);
        $this->assertSame('orders@example.test', $purchaseOrder->fresh()->email_to);
        $this->assertSame('Bakery TingHao Procurement <onboarding@resend.dev>', data_get($resendEmails->sent, '0.parameters.from'));
        $this->assertSame(['bakerytinghao@outlook.com'], data_get($resendEmails->sent, '0.parameters.to'));
        $this->assertSame('supplier-email-draft-'.$draft->id, data_get($resendEmails->sent, '0.options.idempotency_key'));
        $this->assertStringNotContainsString((string) config('resend.api_key'), json_encode($draft->delivery_metadata));
        $this->assertDatabaseHas('agent_tool_calls', [
            'agent_run_id' => $purchaseOrder->agent_run_id,
            'tool_name' => 'send_supplier_email_resend',
            'status' => 'completed',
        ]);
    }

    public function test_duplicate_resend_send_is_prevented(): void
    {
        config($this->resendConfig());
        $admin = $this->user(User::ROLE_ADMIN);
        [, $draft] = $this->approvedEmailWorkflow($admin);
        $draft->update([
            'status' => SupplierEmailDraft::STATUS_SENT,
            'sent_at' => now(),
            'provider' => 'resend',
            'provider_message_id' => 're_existing',
        ]);

        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.send-resend', $draft))
            ->assertRedirect()
            ->assertSessionHasErrors('supplier_email_draft');

        $this->assertSame('re_existing', $draft->fresh()->provider_message_id);
    }

    public function test_resend_provider_failure_keeps_draft_retryable_and_records_failed_audit(): void
    {
        config($this->resendConfig());
        $resendEmails = $this->fakeResendEmails(throw: new RuntimeException('401 secret token should not be shown'));
        Resend::shouldReceive('emails')->once()->andReturn($resendEmails);

        $admin = $this->user(User::ROLE_ADMIN);
        [$purchaseOrder, $draft] = $this->approvedEmailWorkflow($admin);

        $this->actingAs($admin)
            ->post(route('supplier-email-drafts.send-resend', $draft))
            ->assertRedirect()
            ->assertSessionHasErrors('supplier_email_draft');

        $draft->refresh();
        $this->assertSame(SupplierEmailDraft::STATUS_APPROVED, $draft->status);
        $this->assertNull($draft->sent_at);
        $this->assertNull($purchaseOrder->fresh()->sent_at);
        $this->assertSame(SupplierEmailDraft::DELIVERY_FAILED, $draft->delivery_status);
        $this->assertSame('RuntimeException', $draft->send_error_category);
        $this->assertStringNotContainsString('secret token', json_encode($draft->delivery_metadata));
        $this->assertDatabaseHas('agent_tool_calls', [
            'agent_run_id' => $purchaseOrder->agent_run_id,
            'tool_name' => 'send_supplier_email_resend',
            'status' => 'failed',
        ]);
    }

    public function test_supplier_email_draft_page_shows_resend_test_mode_without_demo_action(): void
    {
        config($this->resendConfig());
        $admin = $this->user(User::ROLE_ADMIN);
        [, $draft] = $this->approvedEmailWorkflow($admin);

        $this->actingAs($admin)
            ->get(route('supplier-email-drafts.show', $draft))
            ->assertOk()
            ->assertSee('Send Test Email via Resend')
            ->assertSee('Resend Test Mode')
            ->assertSee('bakerytinghao@outlook.com')
            ->assertSee(route('supplier-email-drafts.send-resend', $draft), false)
            ->assertDontSee(route('supplier-email-drafts.mark-sent', $draft), false);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role, 'status' => User::STATUS_ACTIVE]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function resendConfig(array $overrides = []): array
    {
        return array_merge([
            'autopilot.real_email_enabled' => true,
            'autopilot.resend_test_mode' => true,
            'autopilot.resend_test_recipient' => 'bakerytinghao@outlook.com',
            'autopilot.resend_from_address' => 'verified@example.com',
            'autopilot.resend_from_name' => 'Bakery TingHao Procurement',
            'resend.api_key' => 're_test_key',
            'services.resend.key' => 're_test_key',
        ], $overrides);
    }

    /** @return object{sent: array<int, array<string, mixed>>} */
    private function fakeResendEmails(string $id = 're_test_123', ?RuntimeException $throw = null): object
    {
        return new class($id, $throw)
        {
            public array $sent = [];

            public function __construct(private readonly string $id, private readonly ?RuntimeException $throw) {}

            public function send(array $parameters, array $options = []): Email
            {
                $this->sent[] = ['parameters' => $parameters, 'options' => $options];

                if ($this->throw) {
                    throw $this->throw;
                }

                return Email::from(['id' => $this->id]);
            }
        };
    }

    /** @return array{0: PurchaseOrder, 1: SupplierEmailDraft} */
    private function approvedEmailWorkflow(User $admin, string $supplierEmail = 'bakerytinghao@outlook.com'): array
    {
        $supplier = Supplier::create([
            'name' => 'Email Supplier',
            'contact_person' => 'Sales Team',
            'email' => $supplierEmail,
            'phone' => '0123456789',
        ]);
        $ingredient = Ingredient::create([
            'supplier_id' => $supplier->id,
            'name' => 'Sugar',
            'unit' => 'kg',
            'quantity' => 3,
            'minimum_stock' => 20,
            'cost_price' => 5,
            'selling_price' => 8,
        ]);
        $agentRun = AgentRun::create([
            'user_id' => $admin->id,
            'input_text' => 'Prepare supplier email.',
            'input_type' => 'stock_prediction_restock',
            'status' => AgentRun::STATUS_COMPLETED,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-2026-RESEND',
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
}
