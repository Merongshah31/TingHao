<?php

namespace Database\Seeders;

use App\Models\BackupRecord;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\RestockRequest;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate([
            'email' => 'admin@tinghao.com',
        ], [
            'name' => 'Ting Hao Admin',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);

        $staff = User::updateOrCreate([
            'email' => 'staff@tinghao.com',
        ], [
            'name' => 'Ting Hao Staff',
            'password' => Hash::make('password'),
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
        ]);

        collect([
            ['name' => 'Flour', 'description' => 'Wheat, bread, cake, and specialty flours.'],
            ['name' => 'Sugar', 'description' => 'Sweeteners used for bakery production.'],
            ['name' => 'Dairy', 'description' => 'Milk, butter, cream, and cheese ingredients.'],
            ['name' => 'Leavening', 'description' => 'Yeast, baking powder, and raising agents.'],
            ['name' => 'Packaging', 'description' => 'Boxes, bags, labels, and packing materials.'],
        ])->each(fn (array $category) => Category::updateOrCreate(
            ['name' => $category['name']],
            ['description' => $category['description']]
        ));

        $categories = Category::pluck('id', 'name');

        collect([
            ['name' => 'Store Room', 'type' => StockLocation::TYPE_STORAGE, 'notes' => 'Main usable stock storage.'],
            ['name' => 'Production Area', 'type' => StockLocation::TYPE_PRODUCTION, 'notes' => 'Stock released to bakery production.'],
            ['name' => 'Front Counter', 'type' => StockLocation::TYPE_FRONT, 'notes' => 'Stock held near the sales counter.'],
            ['name' => 'Quarantine / Damaged', 'type' => StockLocation::TYPE_QUARANTINE, 'notes' => 'Damaged or rejected stock waiting for supplier return.'],
        ])->each(fn (array $location) => StockLocation::updateOrCreate(
            ['name' => $location['name']],
            [
                'type' => $location['type'],
                'notes' => $location['notes'],
                'is_active' => true,
            ]
        ));

        collect([
            [
                'name' => 'Golden Grain Supply',
                'contact_person' => 'Mr. Tan',
                'phone' => '+60 12-345 6789',
                'email' => 'orders@goldengrain.test',
                'address' => 'Kuching Wholesale District',
                'notes' => 'Primary flour and grain supplier.',
            ],
            [
                'name' => 'Sweet Pantry Trading',
                'contact_person' => 'Ms. Lim',
                'phone' => '+60 13-222 8899',
                'email' => 'sales@sweetpantry.test',
                'address' => 'Central Market Supplier Row',
                'notes' => 'Sugar and dry baking goods.',
            ],
            [
                'name' => 'Fresh Dairy Partners',
                'contact_person' => 'Ms. Wong',
                'phone' => '+60 16-778 4455',
                'email' => 'supply@freshdairy.test',
                'address' => 'Pending Industrial Estate',
                'notes' => 'Butter, cream, milk powder, and dairy ingredients.',
            ],
            [
                'name' => 'Supplier Ali',
                'contact_person' => 'Ali Rahman',
                'phone' => '+60 14-555 0199',
                'email' => 'orders@supplierali.test',
                'address' => 'Kuching Food Supplier Hub',
                'notes' => 'Demo supplier for Malay restock messages and sugar orders.',
            ],
            [
                'name' => 'BakePro Packaging',
                'contact_person' => 'Mr. Joseph',
                'phone' => '+60 17-888 1200',
                'email' => 'hello@bakepropack.test',
                'address' => 'Samarahan Light Industrial Area',
                'notes' => 'Cake boxes, bread bags, labels, and trays.',
            ],
        ])->each(fn (array $supplier) => Supplier::updateOrCreate(
            ['name' => $supplier['name']],
            $supplier
        ));

        $suppliers = Supplier::pluck('id', 'name');

        collect([
            [
                'name' => 'High Protein Bread Flour',
                'sku' => 'FLR-BREAD-25KG',
                'category' => 'Flour',
                'supplier' => 'Golden Grain Supply',
                'unit' => 'kg',
                'quantity' => 185,
                'minimum_stock' => 60,
                'cost_price' => 4.20,
                'selling_price' => 5.80,
                'expiry_date' => now()->addMonths(5)->toDateString(),
                'notes' => 'Main flour for daily bread production.',
            ],
            [
                'name' => 'Cake Flour',
                'sku' => 'FLR-CAKE-10KG',
                'category' => 'Flour',
                'supplier' => 'Golden Grain Supply',
                'unit' => 'kg',
                'quantity' => 22,
                'minimum_stock' => 30,
                'cost_price' => 4.80,
                'selling_price' => 6.20,
                'expiry_date' => now()->addDays(24)->toDateString(),
                'notes' => 'Low stock demo item.',
            ],
            [
                'name' => 'Caster Sugar',
                'sku' => 'SGR-CASTER-5KG',
                'category' => 'Sugar',
                'supplier' => 'Supplier Ali',
                'unit' => 'kg',
                'quantity' => 96,
                'minimum_stock' => 40,
                'cost_price' => 3.10,
                'selling_price' => 4.35,
                'expiry_date' => now()->addMonths(8)->toDateString(),
                'notes' => 'General sweetener for cakes and pastry. Demo alias: gula for Supplier Ali restock prompt.',
            ],
            [
                'name' => 'Brown Sugar',
                'sku' => 'SGR-BROWN-2KG',
                'category' => 'Sugar',
                'supplier' => 'Sweet Pantry Trading',
                'unit' => 'kg',
                'quantity' => 14,
                'minimum_stock' => 20,
                'cost_price' => 3.60,
                'selling_price' => 4.90,
                'expiry_date' => now()->addDays(18)->toDateString(),
                'notes' => 'Low stock and expiring soon demo item.',
            ],
            [
                'name' => 'Unsalted Butter',
                'sku' => 'DRY-BUTTER-500G',
                'category' => 'Dairy',
                'supplier' => 'Fresh Dairy Partners',
                'unit' => 'kg',
                'quantity' => 12,
                'minimum_stock' => 25,
                'cost_price' => 18.00,
                'selling_price' => 24.00,
                'expiry_date' => now()->addDays(5)->toDateString(),
                'notes' => 'Phase 4 expiry loss demo item: RM216 at risk within 7 days.',
            ],
            [
                'name' => 'Whole Milk Carton',
                'sku' => 'DRY-MILK-1L',
                'category' => 'Dairy',
                'supplier' => 'Fresh Dairy Partners',
                'unit' => 'carton',
                'quantity' => 12,
                'minimum_stock' => 20,
                'cost_price' => 4.40,
                'selling_price' => 5.70,
                'expiry_date' => now()->addDays(9)->toDateString(),
                'notes' => 'Low stock demo item for Qwen supplier confirmation messages.',
            ],
            [
                'name' => 'Instant Yeast',
                'sku' => 'LEV-YEAST-500G',
                'category' => 'Leavening',
                'supplier' => 'Golden Grain Supply',
                'unit' => 'pack',
                'quantity' => 8,
                'minimum_stock' => 15,
                'cost_price' => 6.80,
                'selling_price' => 9.30,
                'expiry_date' => now()->subDays(3)->toDateString(),
                'notes' => 'Expired and low stock demo item.',
            ],
            [
                'name' => 'Baking Powder',
                'sku' => 'LEV-BAKINGPOWDER-1KG',
                'category' => 'Leavening',
                'supplier' => 'Sweet Pantry Trading',
                'unit' => 'kg',
                'quantity' => 36,
                'minimum_stock' => 12,
                'cost_price' => 7.20,
                'selling_price' => 10.50,
                'expiry_date' => now()->addMonths(10)->toDateString(),
                'notes' => 'Stable stock demo item.',
            ],
            [
                'name' => 'Cake Box 8 Inch',
                'sku' => 'PKG-CAKEBOX-8IN',
                'category' => 'Packaging',
                'supplier' => 'BakePro Packaging',
                'unit' => 'pcs',
                'quantity' => 320,
                'minimum_stock' => 100,
                'cost_price' => 0.85,
                'selling_price' => 1.30,
                'expiry_date' => null,
                'notes' => 'Packaging item without expiry date.',
            ],
        ])->each(function (array $ingredient) use ($admin, $categories, $suppliers): void {
            Ingredient::updateOrCreate(
                ['sku' => $ingredient['sku']],
                [
                    'category_id' => $categories[$ingredient['category']] ?? null,
                    'supplier_id' => $suppliers[$ingredient['supplier']] ?? null,
                    'name' => $ingredient['name'],
                    'unit' => $ingredient['unit'],
                    'quantity' => $ingredient['quantity'],
                    'minimum_stock' => $ingredient['minimum_stock'],
                    'cost_price' => $ingredient['cost_price'],
                    'selling_price' => $ingredient['selling_price'],
                    'expiry_date' => $ingredient['expiry_date'],
                    'notes' => $ingredient['notes'],
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]
            );
        });

        $ingredients = Ingredient::pluck('id', 'sku');

        collect([
            ['sku' => 'FLR-BREAD-25KG', 'type' => StockMovement::TYPE_IN, 'quantity' => 200, 'before' => 0, 'after' => 200, 'reason' => 'Opening stock'],
            ['sku' => 'FLR-BREAD-25KG', 'type' => StockMovement::TYPE_OUT, 'quantity' => 15, 'before' => 200, 'after' => 185, 'reason' => 'Production usage'],
            ['sku' => 'FLR-CAKE-10KG', 'type' => StockMovement::TYPE_IN, 'quantity' => 40, 'before' => 0, 'after' => 40, 'reason' => 'Supplier delivery'],
            ['sku' => 'FLR-CAKE-10KG', 'type' => StockMovement::TYPE_OUT, 'quantity' => 18, 'before' => 40, 'after' => 22, 'reason' => 'Sales order preparation'],
            ['sku' => 'SGR-BROWN-2KG', 'type' => StockMovement::TYPE_OUT, 'quantity' => 6, 'before' => 20, 'after' => 14, 'reason' => 'Damaged package removal'],
            ['sku' => 'LEV-YEAST-500G', 'type' => StockMovement::TYPE_OUT, 'quantity' => 4, 'before' => 12, 'after' => 8, 'reason' => 'Expired batch review'],
            ['sku' => 'PKG-CAKEBOX-8IN', 'type' => StockMovement::TYPE_IN, 'quantity' => 320, 'before' => 0, 'after' => 320, 'reason' => 'Monthly packaging order'],
        ])->each(function (array $movement) use ($admin, $staff, $ingredients): void {
            StockMovement::updateOrCreate(
                [
                    'ingredient_id' => $ingredients[$movement['sku']] ?? null,
                    'reason' => $movement['reason'],
                    'quantity_before' => $movement['before'],
                    'quantity_after' => $movement['after'],
                ],
                [
                    'type' => $movement['type'],
                    'quantity' => $movement['quantity'],
                    'notes' => 'Demo movement for presentation.',
                    'created_by' => $movement['type'] === StockMovement::TYPE_OUT ? $staff->id : $admin->id,
                ]
            );
        });

        collect([
            ['sku' => 'FLR-CAKE-10KG', 'status' => RestockRequest::STATUS_REQUESTED, 'notes' => 'Request 2 bags before weekend production.'],
            ['sku' => 'SGR-BROWN-2KG', 'status' => RestockRequest::STATUS_ORDERED, 'notes' => 'Supplier contacted. Awaiting delivery.'],
            ['sku' => 'LEV-YEAST-500G', 'status' => RestockRequest::STATUS_REQUESTED, 'notes' => 'Expired batch needs replacement.'],
        ])->each(function (array $request) use ($admin, $staff, $ingredients): void {
            RestockRequest::updateOrCreate(
                [
                    'ingredient_id' => $ingredients[$request['sku']] ?? null,
                    'notes' => $request['notes'],
                ],
                [
                    'status' => $request['status'],
                    'requested_by' => $staff->id,
                    'completed_by' => null,
                    'completed_at' => null,
                ]
            );
        });

        collect([
            ['key' => 'shop_name', 'value' => 'Ting Hao Bakery Ingredients Shop', 'group' => 'general'],
            ['key' => 'shop_phone', 'value' => '+60 82-555 0188', 'group' => 'general'],
            ['key' => 'shop_email', 'value' => 'hello@tinghao.test', 'group' => 'general'],
            ['key' => 'shop_address', 'value' => 'Lot 88, Bakery Supply Street, Kuching', 'group' => 'general'],
            ['key' => 'low_stock_buffer_days', 'value' => '7', 'group' => 'general'],
        ])->each(fn (array $setting) => SystemSetting::updateOrCreate(
            ['key' => $setting['key']],
            ['value' => $setting['value'], 'group' => $setting['group']]
        ));

        BackupRecord::updateOrCreate(
            ['label' => 'Demo baseline snapshot'],
            [
                'summary' => [
                    'users' => User::count(),
                    'categories' => Category::count(),
                    'ingredients' => Ingredient::count(),
                    'suppliers' => Supplier::count(),
                    'stock_movements' => StockMovement::count(),
                    'restock_requests' => RestockRequest::count(),
                    'created_at' => now()->toDateTimeString(),
                ],
                'created_by' => $admin->id,
            ]
        );
    }
}
