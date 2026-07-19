<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreInventoryDemoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_staff_can_open_core_inventory_pages(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $staff = $this->user(User::ROLE_STAFF);
        $supplier = Supplier::create([
            'name' => 'Supplier Ali',
            'contact_person' => 'Ali',
            'email' => 'ali@example.com',
        ]);
        $ingredient = Ingredient::create([
            'supplier_id' => $supplier->id,
            'name' => 'Demo Sugar',
            'unit' => 'kg',
            'quantity' => 2,
            'minimum_stock' => 10,
            'cost_price' => 5,
            'selling_price' => 8,
            'expiry_date' => now()->addDays(5)->toDateString(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->followingRedirects()->get(route('dashboard'))->assertOk();
        $this->actingAs($staff)->followingRedirects()->get(route('dashboard'))->assertOk();

        foreach ([
            route('admin.dashboard'),
            route('inventory.index'),
            route('inventory.show', $ingredient),
            route('alerts.low-stock'),
            route('expiry.index'),
            route('suppliers.index'),
            route('suppliers.show', $supplier),
            route('reports.index'),
            route('reports.inventory'),
            route('reports.stock'),
            route('reports.low-stock'),
            route('reports.expiry'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        foreach ([
            route('staff.dashboard'),
            route('inventory.index'),
            route('inventory.show', $ingredient),
            route('alerts.low-stock'),
            route('expiry.index'),
            route('suppliers.index'),
            route('suppliers.show', $supplier),
            route('reports.index'),
            route('reports.inventory'),
            route('reports.stock'),
            route('reports.low-stock'),
            route('reports.expiry'),
        ] as $url) {
            $this->actingAs($staff)->get($url)->assertOk();
        }
    }

    public function test_stock_out_cannot_create_negative_stock(): void
    {
        $staff = $this->user(User::ROLE_STAFF);
        $ingredient = Ingredient::create([
            'name' => 'Demo Flour',
            'unit' => 'kg',
            'quantity' => 2,
            'minimum_stock' => 10,
            'cost_price' => 5,
            'selling_price' => 8,
        ]);

        $this->actingAs($staff)
            ->post(route('stock.store', [$ingredient, StockMovement::TYPE_OUT]), [
                'quantity' => 3,
                'reason' => 'Production use',
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseHas('ingredients', [
            'id' => $ingredient->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
