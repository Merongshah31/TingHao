<?php

namespace Tests\Feature;

use App\Mail\PurchaseOrderMail;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_staff_can_view_purchase_orders_but_cannot_manage_them(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $supplier = $this->supplier();
        $ingredient = $this->ingredient();
        $purchaseOrder = $this->purchaseOrder($admin, $supplier, $ingredient);

        $this->actingAs($staff)->get(route('purchase-orders.index'))->assertOk();
        $this->actingAs($staff)->get(route('purchase-orders.show', $purchaseOrder))->assertOk();
        $this->actingAs($staff)->get(route('purchase-orders.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('purchase-orders.store'), [])->assertForbidden();
        $this->actingAs($staff)->get(route('purchase-orders.edit', $purchaseOrder))->assertForbidden();
        $this->actingAs($staff)->put(route('purchase-orders.update', $purchaseOrder), [])->assertForbidden();
        $this->actingAs($staff)->delete(route('purchase-orders.destroy', $purchaseOrder))->assertForbidden();
        $this->actingAs($staff)->post(route('purchase-orders.send-email', $purchaseOrder))->assertForbidden();
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

    private function purchaseOrder(User $admin, Supplier $supplier, Ingredient $ingredient): PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-2026-0001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'order_date' => '2026-06-25',
            'expected_delivery_date' => '2026-06-30',
            'subtotal' => 30,
            'email_to' => $supplier->email,
            'created_by' => $admin->id,
        ]);

        $purchaseOrder->items()->create([
            'ingredient_id' => $ingredient->id,
            'description' => $ingredient->name,
            'quantity' => 6,
            'unit' => 'kg',
            'unit_price' => 5,
            'line_total' => 30,
        ]);

        return $purchaseOrder;
    }
}
