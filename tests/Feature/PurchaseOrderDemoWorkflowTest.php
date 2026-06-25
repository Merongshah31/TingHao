<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\PurchaseOrderDemo;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PurchaseOrderDemoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_run_purchase_order_demo_workflow_without_real_email_or_inventory_update(): void
    {
        Mail::fake();

        $admin = $this->user(User::ROLE_ADMIN);
        $ingredient = Ingredient::create([
            'name' => 'Brown Sugar',
            'unit' => 'kg',
            'quantity' => 2,
            'minimum_stock' => 10,
        ]);

        $this->actingAs($admin)
            ->post(route('po-demo.store'), [
                'supplier_name' => 'Ting Hao Baking Supplier',
                'supplier_email' => 'supplier@example.com',
                'expected_delivery_date' => '2026-06-30',
                'notes' => 'Please confirm availability.',
                'items' => [
                    ['ingredient_name' => 'Brown Sugar', 'quantity' => 15, 'unit' => 'kg', 'unit_price' => 3.50],
                    ['ingredient_name' => 'Cake Flour', 'quantity' => 20, 'unit' => 'kg', 'unit_price' => 4.20],
                ],
            ])
            ->assertRedirect();

        $po = PurchaseOrderDemo::firstOrFail();
        $this->assertSame(PurchaseOrderDemo::STATUS_DRAFT, $po->status);
        $this->assertSame('136.50', $po->subtotal);

        $this->actingAs($admin)
            ->post(route('po-demo.send-email', $po))
            ->assertRedirect()
            ->assertSessionHas('status', __('messages.demo_email_sent_successfully'));

        Mail::assertNothingSent();

        $this->assertSame(PurchaseOrderDemo::STATUS_EMAIL_SENT, $po->fresh()->status);
        $this->assertNotNull($po->fresh()->email_sent_at);

        $this->actingAs($admin)
            ->get(route('po-demo.show', $po))
            ->assertOk()
            ->assertSee('Supplier Email Preview')
            ->assertSee('supplier@example.com');

        $this->actingAs($admin)
            ->post(route('po-demo.confirm', $po))
            ->assertRedirect()
            ->assertSessionHas('status', __('messages.supplier_confirmation_recorded'));

        $this->assertSame(PurchaseOrderDemo::STATUS_SUPPLIER_CONFIRMED, $po->fresh()->status);

        $this->actingAs($admin)
            ->post(route('po-demo.receive', $po), ['mode' => 'partial'])
            ->assertRedirect()
            ->assertSessionHas('status', __('messages.stock_received_demo_successfully'));

        $this->assertSame(PurchaseOrderDemo::STATUS_PARTIALLY_RECEIVED, $po->fresh()->status);

        $this->actingAs($admin)
            ->post(route('po-demo.receive', $po), ['mode' => 'full'])
            ->assertRedirect();

        $this->assertSame(PurchaseOrderDemo::STATUS_RECEIVED, $po->fresh()->status);

        $this->actingAs($admin)
            ->post(route('po-demo.close', $po))
            ->assertRedirect()
            ->assertSessionHas('status', __('messages.purchase_order_closed_successfully'));

        $this->assertSame(PurchaseOrderDemo::STATUS_CLOSED, $po->fresh()->status);
        $this->assertSame('2.00', $ingredient->fresh()->quantity);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_staff_can_view_and_receive_confirmed_demo_po_but_cannot_control_admin_steps(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $po = PurchaseOrderDemo::create([
            'po_number' => 'PO-DEMO-2026-001',
            'supplier_name' => 'Demo Supplier',
            'supplier_email' => 'supplier@example.com',
            'status' => PurchaseOrderDemo::STATUS_SUPPLIER_CONFIRMED,
            'order_date' => '2026-06-25',
            'subtotal' => 35,
            'created_by' => $admin->id,
        ]);
        $po->items()->create([
            'ingredient_name' => 'Brown Sugar',
            'quantity' => 10,
            'unit' => 'kg',
            'unit_price' => 3.50,
            'line_total' => 35,
        ]);

        $this->actingAs($staff)->get(route('po-demo.index'))->assertOk();
        $this->actingAs($staff)->get(route('po-demo.show', $po))->assertOk();
        $this->actingAs($staff)->get(route('po-demo.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('po-demo.store'), [])->assertForbidden();
        $this->actingAs($staff)->post(route('po-demo.send-email', $po))->assertForbidden();
        $this->actingAs($staff)->post(route('po-demo.confirm', $po))->assertForbidden();
        $this->actingAs($staff)->post(route('po-demo.close', $po))->assertForbidden();

        $this->actingAs($staff)
            ->post(route('po-demo.receive', $po), ['mode' => 'full'])
            ->assertRedirect();

        $this->assertSame(PurchaseOrderDemo::STATUS_RECEIVED, $po->fresh()->status);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
