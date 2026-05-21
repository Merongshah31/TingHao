# Ting Hao Core Function Plan

Last reviewed: 2026-05-21

This document defines the planned core functions and subfunctions for the Ting Hao Inventory Management System. It is based on the User Access Function table and is intended to guide development before adding more code.

## 1. Project Goal

Ting Hao is a bakery ingredient inventory management system for managing ingredient records, stock movement, suppliers, expiry dates, reports, and system data.

The system will use:

- Laravel for frontend and backend.
- Supabase PostgreSQL for the database.
- Role-based access for Staff and Admin users.

## 2. User Roles

### Admin

Admin users have full system control, including account creation, ingredient management, supplier management, reports, backup, and system settings.

### Staff

Staff users handle daily operational work such as logging in, editing their profile, viewing inventory, recording stock movement, checking alerts, and viewing reports.

Note:

- This permission plan follows the provided UAF table.
- Some permissions may be adjusted later if the real workflow needs stronger admin control.

## 3. User Access Function Matrix

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

## 4. Core Modules And Subfunctions

### 4.1 User Account Module

Purpose:

- Manage user access to the system.

Subfunctions:

- Create account.
- Log in.
- Log out.
- Edit profile.
- Validate login credentials.
- Protect pages based on role.

Needed pages:

- Login page.
- Profile page.
- Create account page.

Needed database fields:

- User name.
- Email.
- Password.
- Role.
- Account status.

### 4.2 Inventory Management Module

Purpose:

- Manage bakery ingredient records.

Subfunctions:

- Add ingredient.
- Edit ingredient details.
- Delete ingredient record.
- View inventory list.
- Search ingredients.
- Filter by category.
- View ingredient details.

Possible ingredient fields:

- Ingredient name.
- Category.
- Unit type.
- Quantity.
- Minimum stock level.
- Cost price.
- Selling price.
- Supplier.
- Expiry date.
- Notes.

Needed pages:

- Inventory list page.
- Add ingredient page.
- Edit ingredient page.
- Ingredient detail page.

### 4.3 Stock Control Module

Purpose:

- Track stock movement and quantity changes.

Subfunctions:

- Record stock in.
- Record stock out.
- Monitor stock history.
- Keep movement logs.
- Update current quantity after each stock movement.

Stock movement types:

- Stock in.
- Stock out.
- Manual adjustment.
- Expired item removal.

Needed pages:

- Stock movement form.
- Stock history page.
- Ingredient stock history page.

Needed database fields:

- Ingredient ID.
- Movement type.
- Quantity changed.
- Quantity before.
- Quantity after.
- Reason.
- Created by user.
- Movement date.

### 4.4 Low Stock Alert Module

Purpose:

- Help users identify ingredients that need restocking.

Subfunctions:

- View low-stock notifications.
- Compare current quantity with minimum stock level.
- Manage restock process.
- Mark item as restock requested.
- Mark item as restocked.

Needed pages:

- Low-stock list page.
- Restock action page.

### 4.5 Expiry Date Tracking Module

Purpose:

- Track ingredients that are near expiry or already expired.

Subfunctions:

- View expiry dates.
- View near-expiry ingredients.
- View expired ingredients.
- Manage expired items.
- Remove expired stock from inventory.

Needed pages:

- Expiry tracking page.
- Expired item management page.

### 4.6 Supplier Management Module

Purpose:

- Store and manage supplier information.

Subfunctions:

- Add supplier.
- Edit supplier information.
- View supplier details.
- Link supplier to ingredients.
- Search suppliers.

Supplier fields:

- Supplier name.
- Contact person.
- Phone number.
- Email.
- Address.
- Notes.

Needed pages:

- Supplier list page.
- Add supplier page.
- Edit supplier page.
- Supplier detail page.

### 4.7 Reports And Analytics Module

Purpose:

- Provide useful inventory and stock reports.

Subfunctions:

- View inventory report.
- Generate reports.
- Filter by date range.
- Filter by ingredient.
- Filter by category.
- View low-stock report.
- View stock movement report.
- View expiry report.

Possible future export:

- PDF export.
- Excel export.

Needed pages:

- Reports dashboard.
- Inventory report page.
- Stock movement report page.
- Low-stock report page.
- Expiry report page.

### 4.8 System Management Module

Purpose:

- Handle system-level maintenance.

Subfunctions:

- Backup system data.
- Manage system settings.
- Configure low-stock threshold rules.
- Configure shop information.

Needed pages:

- System settings page.
- Backup page.

## 5. Suggested Database Entities

Recommended tables:

| Table | Purpose |
| --- | --- |
| `users` | Stores staff and admin accounts |
| `ingredients` | Stores inventory ingredient/product records |
| `categories` | Stores ingredient categories |
| `suppliers` | Stores supplier information |
| `ingredient_supplier` | Links ingredients to suppliers if many-to-many is needed |
| `stock_movements` | Stores stock in/out/history records |
| `restock_requests` | Stores low-stock restock workflow |
| `system_settings` | Stores configurable system values |

Possible simpler option:

- Use one `role` column in `users` instead of a separate roles table.
- Use `supplier_id` in `ingredients` if each ingredient only has one supplier.

## 6. Recommended Build Priority

### Phase 1: Access Foundation

1. Add `role` and `status` columns to users.
2. Update seed data for admin and staff.
3. Add role middleware.
4. Build basic dashboard after login.

### Phase 2: Inventory Foundation

1. Create ingredient/category tables.
2. Build inventory list.
3. Build add/edit/delete ingredient forms.
4. Add search and filtering.

### Phase 3: Stock Control

1. Create stock movement table.
2. Build stock in form.
3. Build stock out form.
4. Build stock history page.
5. Auto-update ingredient quantity.

### Phase 4: Alerts And Expiry

1. Add minimum stock level logic.
2. Build low-stock list.
3. Add restock process status.
4. Add expiry date tracking.
5. Add expired item management.

### Phase 5: Suppliers

1. Create supplier table.
2. Build supplier CRUD.
3. Link suppliers to ingredients.

### Phase 6: Reports

1. Build inventory report.
2. Build stock movement report.
3. Build low-stock report.
4. Build expiry report.
5. Add export later if needed.

### Phase 7: System Management

1. Add settings table.
2. Build settings page.
3. Add backup process.

## 7. Open Decisions

These should be confirmed before implementation:

- Should Staff be allowed to add new ingredients, or should only Admin add ingredients?
- Should products and ingredients be separate tables, or should everything be called ingredients?
- Should stock out represent sales, usage, damaged items, expired items, or all of them?
- Should Staff be allowed to view expiry dates only, while Admin manages expired items?
- Should reports include export to PDF or Excel in the first version?

## 8. Current Recommendation

For a practical inventory system, the safest permission model is:

- Admin: full control over account, inventory, supplier, reports, backup, and settings.
- Staff: operational access for login, profile, viewing inventory, adding ingredients, stock movement, alert viewing, expiry viewing, supplier viewing, and report viewing.

This document now follows the corrected UAF table. Final permission rules should still be confirmed before coding role middleware.
