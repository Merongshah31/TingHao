# Ting Hao Inventory Management System PRD

Last updated: 2026-05-25

## 1. Product Summary

Ting Hao Inventory Management System is a Laravel-based web application for managing bakery ingredient inventory, stock movement, suppliers, expiry dates, reports, and system data.

The product is designed for internal Ting Hao staff and administrators. It should help the business know what ingredients are available, what needs restocking, what is expiring soon, and how stock changes over time.

## 2. Product Goals

Primary goals:

- Centralize ingredient inventory records.
- Track stock in and stock out clearly.
- Alert users when ingredients are low stock.
- Track ingredient expiry dates.
- Manage supplier information.
- Provide inventory and stock reports.
- Support admin and staff role-based access.
- Prepare the system for future POS integration.

Secondary goals:

- Provide a professional dashboard for daily operations.
- Support Supabase PostgreSQL as the database.
- Support Render deployment.
- Keep future API integration clean and secure.

## 3. Target Users

### Admin

Admin users manage the full system.

Admin responsibilities:

- Manage staff/admin accounts.
- Add, edit, and delete ingredient records.
- Record stock movement.
- Manage low-stock restock process.
- Manage expired stock.
- Manage suppliers.
- View and generate reports.
- Upload and download Excel reports.
- Manage system settings and backups.

### Staff

Staff users handle daily inventory operations.

Staff responsibilities:

- Log in to the system.
- Add new ingredient records.
- View inventory.
- Record stock in and stock out.
- View low-stock alerts.
- View expiry dates.
- View supplier details.
- View reports.

## 4. Problem Statement

Ting Hao needs a structured inventory system because manual tracking makes it difficult to:

- Know current stock levels.
- Identify low-stock ingredients early.
- Track expired or expiring ingredients.
- Review stock movement history.
- Understand supplier relationships.
- Prepare reports for management.
- Connect future POS sales to stock deduction.

Without a system, stock mistakes can lead to overbuying, shortage, expired ingredients, and unclear accountability.

## 5. Current System Status

Implemented:

- Public Ting Hao landing page.
- Login/logout authentication.
- Admin and staff role routing.
- Admin/staff dashboard.
- Dashboard analytics visualization.
- Ingredient inventory CRUD.
- Category support.
- Supplier support.
- Stock in and stock out.
- Stock movement history.
- Low-stock alerts.
- Restock request workflow.
- Expiry tracking.
- Expired stock removal.
- Reports pages.
- System settings.
- Backup snapshot records.
- Supabase PostgreSQL connection setup.
- Render Docker deployment setup.
- Demo seed/mock data.

Not yet implemented:

- Staff account management UI.
- Profile editing.
- Excel report upload/download.
- POS integration.
- JSON REST API.
- Product/recipe mapping for POS stock deduction.
- Purchase order module.
- Real public search.
- Real map/contact integration.

## 6. Scope

### In Scope

- Laravel web application.
- Supabase PostgreSQL database.
- Admin and staff authentication.
- Ingredient inventory management.
- Stock movement management.
- Low-stock and expiry workflows.
- Supplier management.
- Reports and dashboard analytics.
- System settings and backup snapshots.
- Render deployment.
- Future API-ready architecture.

### Out Of Scope For Current Version

- Full accounting system.
- Payment processing.
- Customer management.
- Full POS replacement.
- Multi-branch warehouse management.
- Barcode scanner hardware support.
- Mobile app.
- Public e-commerce checkout.

These can be considered future enhancements.

## 7. User Access Function Matrix

| Function | Activity | Admin | Staff |
| --- | --- | --- | --- |
| User Account | Create Account | Yes | No |
| User Account | Log In | Yes | Yes |
| User Account | Edit Profile | Yes | Yes |
| Inventory Management | Add Ingredients | Yes | Yes |
| Inventory Management | Edit Ingredient Details | Yes | No |
| Inventory Management | Delete Ingredient Record | Yes | No |
| Inventory Management | View Inventory | Yes | Yes |
| Stock Control | Record Stock In | Yes | Yes |
| Stock Control | Record Stock Out | Yes | Yes |
| Stock Control | Monitor Stock History | Yes | Yes |
| Low Stock Alert | View Low Stock Notification | Yes | Yes |
| Low Stock Alert | Manage Restock Process | Yes | No |
| Expiry Date Tracking | View Expiry Dates | Yes | Yes |
| Expiry Date Tracking | Manage Expired Items | Yes | No |
| Supplier Management | Add Supplier | Yes | No |
| Supplier Management | Edit Supplier Information | Yes | No |
| Supplier Management | View Supplier Details | Yes | Yes |
| Reports & Analytics | View Inventory Report | Yes | Yes |
| Reports & Analytics | Generate Reports | Yes | No |
| System Management | Backup System Data | Yes | No |
| System Management | Manage System Settings | Yes | No |

## 8. Functional Requirements

### 8.1 Authentication And User Access

Requirements:

- Users must log in with email and password.
- Users must have a role: `admin` or `staff`.
- Inactive users must not be allowed to log in.
- Authenticated users must be redirected to the correct dashboard.
- Admin and staff must see role-appropriate actions.

Acceptance criteria:

- Valid admin can access admin dashboard.
- Valid staff can access staff dashboard.
- Staff cannot access admin-only pages.
- Invalid login shows an error.
- Logout ends the session.

### 8.2 Dashboard

Requirements:

- Dashboard must show high-level system metrics.
- Dashboard must show analytics visualization.
- Dashboard must provide quick access to daily work areas.

Current dashboard metrics:

- Ingredient count.
- Low-stock count.
- Expiring count.
- Supplier count.
- Stock movement count.
- Open restock request count.

Current analytics:

- Inventory value.
- Stock health percentage.
- Stock in/out movement mix.
- Lowest-stock item visualization.
- Recent stock movement badges.

Acceptance criteria:

- Dashboard loads for authenticated users.
- Dashboard data comes from real database records.
- Admin sees system controls.
- Staff sees operational controls only.

### 8.3 Inventory Management

Requirements:

- Admin and staff can add ingredients.
- Admin and staff can view inventory.
- Admin can edit ingredient details.
- Admin can delete ingredient records.
- Inventory can be searched and filtered.

Ingredient fields:

- Name.
- SKU.
- Category.
- Supplier.
- Unit.
- Quantity.
- Minimum stock.
- Cost price.
- Selling price.
- Expiry date.
- Notes.

Acceptance criteria:

- Users can create ingredient records.
- Inventory list shows current quantity.
- Low-stock state is based on minimum stock.
- Admin-only edit/delete actions are protected.

### 8.4 Stock Control

Requirements:

- Admin and staff can record stock in.
- Admin and staff can record stock out.
- Stock movement must update current ingredient quantity.
- Stock movement must keep before and after quantity.
- Stock out must not create negative stock.

Stock out reasons may include:

- Sales.
- Production usage.
- Damaged items.
- Expired items.
- Manual adjustment.

Acceptance criteria:

- Stock in increases quantity.
- Stock out decreases quantity.
- Each movement creates an audit record.
- Movement history can be filtered.

### 8.5 Low Stock Alert

Requirements:

- System must identify low-stock ingredients.
- Low stock is when quantity is less than or equal to minimum stock.
- Admin can create and update restock requests.
- Staff can view low-stock notifications.

Acceptance criteria:

- Low-stock page lists matching ingredients.
- Restock request status can be updated by admin.
- Staff cannot manage restock status.

### 8.6 Expiry Date Tracking

Requirements:

- System must show expiring-soon ingredients.
- System must show expired ingredients.
- Admin can remove expired stock.
- Staff can view expiry dates only.

Acceptance criteria:

- Expiring-soon list uses 30-day window.
- Expired list uses dates before today.
- Expired removal records a stock-out movement.

### 8.7 Supplier Management

Requirements:

- Admin can add suppliers.
- Admin can edit suppliers.
- Admin and staff can view supplier details.
- Ingredients can be linked to suppliers.

Supplier fields:

- Name.
- Contact person.
- Phone.
- Email.
- Address.
- Notes.

Acceptance criteria:

- Supplier list is searchable.
- Supplier detail shows linked information.
- Staff cannot add or edit supplier records.

### 8.8 Reports And Analytics

Requirements:

- Admin and staff can view reports.
- Admin can generate summary reports.
- Admin should be able to upload and download Excel reports.

Current reports:

- Inventory report.
- Stock movement report.
- Low-stock report.
- Expiry report.
- Generated summary report.

Future Excel requirements:

- Admin can export inventory report to Excel.
- Admin can export stock movement report to Excel.
- Admin can upload Excel import files where appropriate.
- Staff can view reports but cannot upload or download Excel reports unless changed later.

Acceptance criteria:

- Reports display accurate database data.
- Admin-only generated summary is protected.
- Excel actions are admin-only when implemented.

### 8.9 System Management

Requirements:

- Admin can manage system settings.
- Admin can create backup snapshot records.

Acceptance criteria:

- Staff cannot access system settings.
- Backup snapshot records system counts and metadata.

## 9. Future API Requirements

The current system uses Laravel web routes. A future JSON API should be added for POS or external integrations.

Recommended API style:

- REST API.
- Base path: `/api/v1`.
- Authentication: Laravel Sanctum token.
- One token per POS device or external system.

### POS Integration Requirement

Goal:

- When POS records a sale, Ting Hao should deduct inventory stock automatically.

Proposed endpoint:

```http
POST /api/v1/pos/sales
Authorization: Bearer {token}
Content-Type: application/json
```

Example request:

```json
{
  "receipt_no": "POS-1001",
  "sold_at": "2026-05-25T15:30:00+08:00",
  "items": [
    {
      "sku": "CAKE-CHOC",
      "quantity": 2
    }
  ]
}
```

Expected behavior:

- Validate API token.
- Validate sale payload.
- Match sold item by SKU.
- Deduct related ingredient quantity.
- Create stock-out movement records.
- Return success or validation error response.

Future required tables:

- `pos_sales`
- `pos_sale_items`
- `products`
- `product_ingredients`
- `api_tokens` if not using Sanctum default tables directly.

## 10. Data Requirements

Current tables:

- `users`
- `categories`
- `ingredients`
- `suppliers`
- `stock_movements`
- `restock_requests`
- `system_settings`
- `backup_records`
- Laravel session/cache/job tables.

Recommended future tables:

- `pos_sales`
- `pos_sale_items`
- `products`
- `product_ingredients`
- `purchase_orders`
- `purchase_order_items`

## 11. Non-Functional Requirements

### Security

- Passwords must be hashed.
- Admin-only routes must be protected.
- Staff must not access restricted admin workflows.
- `.env` and credential files must not be committed.
- API tokens must not be exposed in frontend JavaScript.

### Performance

- Dashboard should load quickly with summarized queries.
- Reports should support filtering to avoid loading too much data.
- Render deployment should cache Laravel config, routes, and views.

### Reliability

- Stock movement should preserve audit history.
- Stock out should not allow negative quantity.
- Migrations should run safely in production using `php artisan migrate --force`.

### Maintainability

- Use controllers for backend logic.
- Use Eloquent models for database access.
- Keep documentation updated when modules change.
- Keep permissions clear in route middleware.

### Deployment

- App should deploy to Render using Docker.
- Runtime should use PHP 8.4 FPM.
- Database should use Supabase PostgreSQL.
- Build should include Composer production install and Vite asset build.

## 12. Success Metrics

Product success can be measured by:

- Staff can record daily stock movement without spreadsheet use.
- Admin can identify low-stock items quickly.
- Admin can identify expiring and expired ingredients quickly.
- Supplier records are centralized.
- Reports can support inventory review.
- POS integration can later reduce manual stock-out work.

## 13. Development Roadmap

### Completed

- Phase 1: Access foundation.
- Phase 2: Inventory foundation.
- Phase 3: Stock control.
- Phase 4: Alerts and expiry.
- Phase 5: Suppliers.
- Phase 6: Reports.
- Phase 7: System management.
- Dashboard analytics visualization.
- Render deployment setup.
- Supabase setup documentation.

### Recommended Next Phase

Phase 8: Excel Reports

- Add Excel export for inventory.
- Add Excel export for stock movement.
- Add Excel export for low-stock report.
- Add Admin-only Excel upload/import where needed.

Phase 9: User Management

- Add Admin page to create staff accounts.
- Add Admin page to update staff status.
- Add profile edit page.

Phase 10: POS/API Integration

- Add Laravel Sanctum.
- Add API token management.
- Add POS sale endpoint.
- Add product and recipe mapping.
- Add automatic stock deduction from POS sales.

## 14. Open Questions

- Which POS system will be connected?
- Does the POS support API/webhook, or only Excel export?
- Should SKU be mandatory before POS integration?
- Should ingredient quantity use decimal values for all units?
- Should sales and product recipes be managed inside Ting Hao or imported from POS?
- Should Excel upload update stock, ingredients, suppliers, or only reports?

## 15. Related Documentation

- `docs/current-function-inventory.md`
- `docs/core-function-plan.md`
- `docs/backend-api.md`
- `docs/supabase-setup.md`
- `docs/render-deploy.md`
