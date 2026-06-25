<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\RestockRequest;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_low_stock_workflow_actions(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $ingredient = $this->lowStockIngredient();

        $this->actingAs($staff)
            ->get(route('alerts.low-stock'))
            ->assertOk()
            ->assertSee($ingredient->name)
            ->assertSee('Request Stock')
            ->assertSee('Stock In')
            ->assertSee('Stock Out')
            ->assertDontSee('name="status"', false);
    }

    public function test_staff_can_submit_restock_request_but_duplicate_active_request_is_blocked(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $ingredient = $this->lowStockIngredient();

        $this->actingAs($staff)
            ->post(route('alerts.restock.request', $ingredient), [
                'notes' => 'Please restock soon.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', __('messages.restock_request_submitted'));

        $this->assertDatabaseHas('restock_requests', [
            'ingredient_id' => $ingredient->id,
            'status' => RestockRequest::STATUS_REQUESTED,
            'requested_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->post(route('alerts.restock.request', $ingredient))
            ->assertRedirect()
            ->assertSessionHas('status', __('messages.restock_request_exists'));

        $this->assertSame(1, RestockRequest::where('ingredient_id', $ingredient->id)->count());
    }

    public function test_staff_cannot_update_restock_status_but_admin_can(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $admin = $this->user(User::ROLE_ADMIN);
        $ingredient = $this->lowStockIngredient();
        $restockRequest = $ingredient->restockRequests()->create([
            'status' => RestockRequest::STATUS_REQUESTED,
            'requested_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->patch(route('alerts.restock.update', $restockRequest), [
                'status' => RestockRequest::STATUS_COMPLETED,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('alerts.restock.update', $restockRequest), [
                'status' => RestockRequest::STATUS_ORDERED,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('restock_requests', [
            'id' => $restockRequest->id,
            'status' => RestockRequest::STATUS_ORDERED,
        ]);
    }

    public function test_staff_can_record_stock_in_and_stock_out(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $ingredient = $this->lowStockIngredient(['quantity' => 5, 'minimum_stock' => 10]);

        $this->actingAs($staff)
            ->get(route('stock.create', [$ingredient, StockMovement::TYPE_IN]))
            ->assertOk();

        $this->actingAs($staff)
            ->post(route('stock.store', [$ingredient, StockMovement::TYPE_IN]), [
                'quantity' => 4,
                'reason' => 'Supplier delivery',
            ])
            ->assertRedirect(route('inventory.show', $ingredient));

        $this->actingAs($staff)
            ->get(route('stock.create', [$ingredient, StockMovement::TYPE_OUT]))
            ->assertOk();

        $this->actingAs($staff)
            ->post(route('stock.store', [$ingredient, StockMovement::TYPE_OUT]), [
                'quantity' => 2,
                'reason' => 'Daily production',
            ])
            ->assertRedirect(route('inventory.show', $ingredient));

        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredient->id,
            'quantity' => 7,
        ]);
        $this->assertSame(2, StockMovement::where('ingredient_id', $ingredient->id)->count());
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
    private function lowStockIngredient(array $overrides = []): Ingredient
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
}
