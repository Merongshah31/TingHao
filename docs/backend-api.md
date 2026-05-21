# Ting Hao Backend API Documentation

Last reviewed: 2026-05-21

This document explains the current Laravel backend surface for Ting Hao and the recommended future JSON API structure. The current system is a Laravel Blade application, so most backend actions are web routes that return HTML pages or redirects instead of JSON.

## 1. Backend Architecture

```text
Browser
  -> Laravel web routes
  -> Controllers
  -> Eloquent models
  -> Supabase PostgreSQL
```

Current backend style:

- Authentication uses Laravel session login.
- Forms use CSRF protection.
- Routes live in `routes/web.php`.
- Controllers live in `app/Http/Controllers`.
- Database access uses Eloquent models.
- Supabase is used as the PostgreSQL database.

Current API status:

- No public JSON API routes are implemented yet.
- No `routes/api.php` endpoints are implemented yet.
- POS/mobile integration should use a future `/api/*` route group with token authentication.

## 2. Authentication

### Login Page

| Method | URI | Route Name | Controller |
| --- | --- | --- | --- |
| GET | `/login` | `login` | `LoginController@create` |

Purpose:

- Shows the login form.

Access:

- Guest only.

### Login Submit

| Method | URI | Route Name | Controller |
| --- | --- | --- | --- |
| POST | `/login` | `login.store` | `LoginController@store` |

Purpose:

- Authenticates admin or staff users.
- Rejects inactive accounts.
- Redirects authenticated users to their dashboard.

Request fields:

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `email` | string | Yes | User email address |
| `password` | string | Yes | User password |
| `remember` | boolean | No | Remember-me checkbox |

Seed demo accounts:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@tinghao.com` | `password` |
| Staff | `staff@tinghao.com` | `password` |

### Logout

| Method | URI | Route Name | Controller |
| --- | --- | --- | --- |
| POST | `/logout` | `logout` | `LoginController@destroy` |

Purpose:

- Ends the current session.

Access:

- Authenticated users.

## 3. Role Access

Roles are stored in the `users.role` column.

Supported roles:

| Role | Meaning |
| --- | --- |
| `admin` | Full system control |
| `staff` | Daily operation access |

Role middleware:

```php
role:admin
role:staff
role:admin,staff
```

## 4. Dashboard Routes

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/dashboard` | `dashboard` | Authenticated | Redirect user to role dashboard |
| GET | `/admin/dashboard` | `admin.dashboard` | Admin | Admin dashboard |
| GET | `/staff/dashboard` | `staff.dashboard` | Staff | Staff dashboard |

Dashboard data includes:

- Ingredient count.
- Low-stock count.
- Expiring count.
- Supplier count.
- Stock movement count.
- Open restock request count.
- Inventory value.
- Stock health percentage.
- Stock in/out movement mix.
- Lowest stock list.
- Recent stock movements.

## 5. Inventory Backend

Model:

- `App\Models\Ingredient`

Related models:

- `Category`
- `Supplier`
- `StockMovement`
- `RestockRequest`
- `User`

### Inventory Routes

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/inventory` | `inventory.index` | Admin, Staff | List and search ingredients |
| GET | `/inventory/create` | `inventory.create` | Admin, Staff | Show add ingredient form |
| POST | `/inventory` | `inventory.store` | Admin, Staff | Create ingredient |
| GET | `/inventory/{ingredient}` | `inventory.show` | Admin, Staff | View ingredient detail |
| GET | `/inventory/{ingredient}/edit` | `inventory.edit` | Admin | Show edit form |
| PUT | `/inventory/{ingredient}` | `inventory.update` | Admin | Update ingredient |
| DELETE | `/inventory/{ingredient}` | `inventory.destroy` | Admin | Delete ingredient |

Ingredient fields:

| Field | Type | Notes |
| --- | --- | --- |
| `category_id` | foreign id | Optional category |
| `supplier_id` | foreign id | Optional supplier |
| `name` | string | Ingredient name |
| `sku` | string | Optional unique stock keeping unit |
| `unit` | string | Example: kg, pack, botol |
| `quantity` | decimal | Current stock quantity |
| `minimum_stock` | decimal | Low-stock threshold |
| `cost_price` | decimal | Internal cost price |
| `selling_price` | decimal | Selling price if needed |
| `expiry_date` | date | Optional expiry date |
| `notes` | text | Optional notes |
| `created_by` | foreign id | User who created the record |
| `updated_by` | foreign id | Last user who updated the record |

## 6. Stock Movement Backend

Model:

- `App\Models\StockMovement`

Movement types:

| Type | Meaning |
| --- | --- |
| `in` | Stock added |
| `out` | Stock removed |

Stock out can represent:

- Sales.
- Production usage.
- Damaged items.
- Expired items.
- Manual outgoing stock.

### Stock Routes

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/stock/history` | `stock.index` | Admin, Staff | View movement history |
| GET | `/inventory/{ingredient}/stock/{type}` | `stock.create` | Admin, Staff | Show stock in/out form |
| POST | `/inventory/{ingredient}/stock/{type}` | `stock.store` | Admin, Staff | Record stock movement |

Stock movement fields:

| Field | Type | Notes |
| --- | --- | --- |
| `ingredient_id` | foreign id | Ingredient being changed |
| `type` | string | `in` or `out` |
| `quantity` | decimal | Quantity changed |
| `quantity_before` | decimal | Stock before movement |
| `quantity_after` | decimal | Stock after movement |
| `reason` | string | Optional reason |
| `notes` | text | Optional notes |
| `created_by` | foreign id | User who recorded movement |

Backend behavior:

- Stock in increases ingredient quantity.
- Stock out decreases ingredient quantity.
- Stock out should not allow negative stock.
- Every stock movement records before and after quantity.

## 7. Low Stock Backend

Models:

- `Ingredient`
- `RestockRequest`

### Low Stock Routes

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/alerts/low-stock` | `alerts.low-stock` | Admin, Staff | View low-stock ingredients |
| POST | `/alerts/low-stock/{ingredient}/restock` | `alerts.restock.request` | Admin | Create restock request |
| PATCH | `/alerts/restock/{restockRequest}` | `alerts.restock.update` | Admin | Update restock status |

Low-stock rule:

```text
ingredient.quantity <= ingredient.minimum_stock
```

## 8. Expiry Backend

Model:

- `Ingredient`

### Expiry Routes

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/expiry` | `expiry.index` | Admin, Staff | View expiring and expired ingredients |
| POST | `/expiry/{ingredient}/remove` | `expiry.remove` | Admin | Remove expired stock |

Expiry rules:

| Scope | Rule |
| --- | --- |
| Expiring soon | `expiry_date` is within the next 30 days |
| Expired | `expiry_date` is before today |

Expired stock removal:

- Sets stock quantity down through stock-out behavior.
- Records the removal in stock movement history.

## 9. Supplier Backend

Model:

- `App\Models\Supplier`

### Supplier Routes

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/suppliers` | `suppliers.index` | Admin, Staff | List suppliers |
| GET | `/suppliers/create` | `suppliers.create` | Admin | Show add supplier form |
| POST | `/suppliers` | `suppliers.store` | Admin | Create supplier |
| GET | `/suppliers/{supplier}` | `suppliers.show` | Admin, Staff | View supplier detail |
| GET | `/suppliers/{supplier}/edit` | `suppliers.edit` | Admin | Show edit form |
| PUT | `/suppliers/{supplier}` | `suppliers.update` | Admin | Update supplier |

Supplier fields:

| Field | Type | Notes |
| --- | --- | --- |
| `name` | string | Supplier name |
| `contact_person` | string | Optional |
| `phone` | string | Optional |
| `email` | string | Optional |
| `address` | text | Optional |
| `notes` | text | Optional |

## 10. Reports Backend

Controller:

- `ReportController`

### Report Routes

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/reports` | `reports.index` | Admin, Staff | Reports dashboard |
| GET | `/reports/inventory` | `reports.inventory` | Admin, Staff | Inventory report |
| GET | `/reports/stock` | `reports.stock` | Admin, Staff | Stock movement report |
| GET | `/reports/low-stock` | `reports.low-stock` | Admin, Staff | Low-stock report |
| GET | `/reports/expiry` | `reports.expiry` | Admin, Staff | Expiry report |
| GET | `/reports/generated-summary` | `reports.generated-summary` | Admin | Generated summary report |

Confirmed future enhancement:

- Admin can upload and download Excel reports.
- Staff can view reports but should not upload/download Excel unless requirements change.

## 11. System Backend

Models:

- `SystemSetting`
- `BackupRecord`

### System Routes

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/system/settings` | `system.settings` | Admin | View settings |
| PUT | `/system/settings` | `system.settings.update` | Admin | Update settings |
| GET | `/system/backups` | `system.backups` | Admin | View backup snapshots |
| POST | `/system/backups` | `system.backups.create` | Admin | Create backup snapshot |

## 12. Recommended Future JSON API

For POS, mobile app, or external systems, add routes in `routes/api.php`.

Recommended base path:

```text
/api/v1
```

Recommended authentication:

- Laravel Sanctum token authentication.
- One token per POS device or external client.
- Never expose admin session cookies to POS devices.

### Proposed POS Sale Endpoint

```http
POST /api/v1/pos/sales
Authorization: Bearer {token}
Content-Type: application/json
```

Example request:

```json
{
  "receipt_no": "POS-1001",
  "sold_at": "2026-05-21T15:30:00+08:00",
  "items": [
    {
      "sku": "CAKE-CHOC",
      "quantity": 2
    }
  ]
}
```

Expected backend behavior:

1. Validate token.
2. Validate receipt number and sale items.
3. Find mapped product or ingredient by SKU.
4. Deduct ingredient stock.
5. Create stock-out movement records.
6. Return success response.

Example response:

```json
{
  "status": "success",
  "message": "Sale synced and inventory updated.",
  "receipt_no": "POS-1001"
}
```

### Proposed Inventory JSON Endpoints

| Method | URI | Purpose |
| --- | --- | --- |
| GET | `/api/v1/ingredients` | List ingredients |
| GET | `/api/v1/ingredients/{id}` | View ingredient |
| POST | `/api/v1/ingredients` | Create ingredient |
| PATCH | `/api/v1/ingredients/{id}` | Update ingredient |
| POST | `/api/v1/ingredients/{id}/stock-in` | Record stock in |
| POST | `/api/v1/ingredients/{id}/stock-out` | Record stock out |
| GET | `/api/v1/stock-movements` | List stock movements |
| GET | `/api/v1/reports/inventory` | Inventory report JSON |
| GET | `/api/v1/reports/low-stock` | Low-stock report JSON |

## 13. Backend Files Reference

Controllers:

- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/IngredientController.php`
- `app/Http/Controllers/StockMovementController.php`
- `app/Http/Controllers/LowStockController.php`
- `app/Http/Controllers/ExpiryController.php`
- `app/Http/Controllers/SupplierController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/SystemController.php`

Models:

- `app/Models/User.php`
- `app/Models/Category.php`
- `app/Models/Ingredient.php`
- `app/Models/StockMovement.php`
- `app/Models/RestockRequest.php`
- `app/Models/Supplier.php`
- `app/Models/SystemSetting.php`
- `app/Models/BackupRecord.php`

Route files:

- `routes/web.php`
- `routes/api.php` can be added later for JSON APIs.

## 14. Current Backend Limitations

- No JSON API is implemented yet.
- No API token authentication is implemented yet.
- No POS sale table exists yet.
- No product recipe mapping exists yet.
- Excel upload/download is confirmed but not implemented yet.
- Current backend actions mainly return Blade views and redirects.
