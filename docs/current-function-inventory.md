# Ting Hao Current Function Inventory

Last reviewed: 2026-05-21

This document identifies what the project already has now, based on the actual Laravel files in the repository. It separates implemented functions from planned features so the next development work can be chosen clearly.

## 1. Project Status Summary

The project is currently a Laravel 13 application with:

- A public landing page for Ting Hao.
- A visual admin/staff login page.
- Real Laravel login/logout handling.
- Role-aware admin and staff dashboard routing.
- Dashboard analytics with inventory value, stock health, movement mix, lowest-stock visualization, and recent movement badges.
- Inventory category and ingredient database tables.
- Inventory list, add, view, edit, delete, search, and category filter pages.
- Stock movement database table.
- Stock-in and stock-out recording.
- Stock movement history with filters.
- Automatic ingredient quantity updates after stock movement.
- Low-stock alert page.
- Restock request workflow for low-stock items.
- Expiry tracking for expiring-soon and expired ingredients.
- Expired stock removal action that records stock-out history.
- Supplier database table.
- Supplier list, add, view, edit, and search pages.
- Ingredients can be linked to a supplier.
- Reports dashboard.
- Inventory, stock movement, low-stock, and expiry report pages.
- Admin-only generated summary report.
- System settings page.
- Backup snapshot records.
- A shared Blade layout.
- A custom CSS theme for the public and login pages.
- Default Laravel database tables for users, sessions, cache, and queues.
- Basic starter tests.
- Render deployment configuration with Docker, Nginx, PHP 8.4 FPM, Vite build, and Supabase environment variables.

The inventory management system now has authentication, role-aware dashboards, inventory foundation, stock control, low-stock alerts, restock workflow, expiry tracking, supplier management, reports, and system management.

Latest recovery note:

- This documentation was restored from Git history after the docs directory was deleted in a previous commit.
- `docs/encrypt.md` was intentionally not restored because it contained credential-looking content and is now ignored by Git.

## 2. Implemented User-Facing Pages

### Home Page

Route:

- `GET /`
- Route name: `home`
- View: `resources/views/home.blade.php`

Current functions:

- Shows Ting Hao public website landing page.
- Displays sticky top navigation.
- Includes navigation links for Home, About, Products, and Contact sections.
- Includes a search input placeholder for ingredients.
- Includes an Admin Login button linking to the login page.
- Shows a hero section with external bakery image.
- Shows business mission copy.
- Shows statistics cards: organic certified, daily freshness, artisan clients.
- Shows curated product cards for premium flours, natural sugars, and professional tools.
- Shows contact details, opening hours, warehouse address, and a map placeholder.
- Shows footer links for policy, terms, shipping, and wholesale inquiry.

Current limitations:

- Search input is visual only and does not search data.
- Product cards are static content, not database-driven.
- Contact details are placeholder content.
- Map area is a visual placeholder, not a real embedded map.
- Footer links are placeholders.

### Login Page

Route:

- `GET /login`
- Route name: `login`
- View: `resources/views/auth/login.blade.php`

Current functions:

- Shows a polished login screen for admin/staff access.
- Includes email and password fields.
- Includes remember-me checkbox.
- Includes forgot-password link placeholder.
- Includes CSRF token field in the form.
- Includes sign-in button.
- Includes privacy and support link placeholders.
- Submits to the Laravel login route.
- Rejects invalid or inactive accounts.
- Redirects authenticated users to their role dashboard.

Current limitations:

- Forgot password is visual only.
- Role handling currently covers dashboard access only. Module-level permission rules still need to be added as each module is built.

### Admin And Staff Dashboard

Routes:

- `GET /admin/dashboard`
- `GET /staff/dashboard`

Current functions:

- Redirects authenticated users to the correct role dashboard.
- Shows summary metric cards for ingredients, low stock, expiry, suppliers, stock movements, and restock requests.
- Shows visual analytics for inventory value, stock health, and stock in/out flow.
- Shows lowest-stock items with progress indicators.
- Shows recent stock movement with stock-in and stock-out badges.
- Shows shortcut panels for inventory, alerts, suppliers, reports, settings, and backups based on role.

Current limitations:

- Analytics are generated from current inventory and stock movement records only.
- No sales/POS analytics are connected yet.
- No chart JavaScript library is used; visualizations are CSS-based.

## 3. Routing

Route file:

- `routes/web.php`

Implemented routes:

| Method | URI | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/` | `home` | Show public Ting Hao landing page |
| GET | `/login` | `login` | Show visual staff/admin login page |
| POST | `/login` | `login.store` | Authenticate user credentials |
| GET | `/dashboard` | `dashboard` | Redirect user to role dashboard |
| GET | `/admin/dashboard` | `admin.dashboard` | Show protected admin dashboard |
| GET | `/staff/dashboard` | `staff.dashboard` | Show protected staff dashboard |
| GET | `/inventory` | `inventory.index` | Show searchable inventory list |
| GET | `/inventory/create` | `inventory.create` | Show add ingredient form |
| POST | `/inventory` | `inventory.store` | Store new ingredient |
| GET | `/inventory/{ingredient}` | `inventory.show` | Show ingredient detail |
| GET | `/inventory/{ingredient}/edit` | `inventory.edit` | Show edit ingredient form |
| PUT | `/inventory/{ingredient}` | `inventory.update` | Update ingredient |
| DELETE | `/inventory/{ingredient}` | `inventory.destroy` | Delete ingredient |
| GET | `/stock/history` | `stock.index` | Show stock movement history |
| GET | `/inventory/{ingredient}/stock/{type}` | `stock.create` | Show stock-in or stock-out form |
| POST | `/inventory/{ingredient}/stock/{type}` | `stock.store` | Record stock-in or stock-out |
| GET | `/alerts/low-stock` | `alerts.low-stock` | Show low-stock alerts |
| POST | `/alerts/low-stock/{ingredient}/restock` | `alerts.restock.request` | Create restock request |
| PATCH | `/alerts/restock/{restockRequest}` | `alerts.restock.update` | Update restock status |
| GET | `/expiry` | `expiry.index` | Show expiry tracking |
| POST | `/expiry/{ingredient}/remove` | `expiry.remove` | Remove expired stock |
| GET | `/suppliers` | `suppliers.index` | Show supplier list |
| GET | `/suppliers/create` | `suppliers.create` | Show add supplier form |
| POST | `/suppliers` | `suppliers.store` | Store new supplier |
| GET | `/suppliers/{supplier}` | `suppliers.show` | Show supplier detail |
| GET | `/suppliers/{supplier}/edit` | `suppliers.edit` | Show edit supplier form |
| PUT | `/suppliers/{supplier}` | `suppliers.update` | Update supplier |
| GET | `/reports` | `reports.index` | Show reports dashboard |
| GET | `/reports/inventory` | `reports.inventory` | Show inventory report |
| GET | `/reports/stock` | `reports.stock` | Show stock movement report |
| GET | `/reports/low-stock` | `reports.low-stock` | Show low-stock report |
| GET | `/reports/expiry` | `reports.expiry` | Show expiry report |
| GET | `/reports/generated-summary` | `reports.generated-summary` | Show admin generated summary report |
| GET | `/system/settings` | `system.settings` | Show system settings |
| PUT | `/system/settings` | `system.settings.update` | Update system settings |
| GET | `/system/backups` | `system.backups` | Show backup records |
| POST | `/system/backups` | `system.backups.create` | Create backup snapshot |
| POST | `/logout` | `logout` | End authenticated session |

Current routing limitations:

- No inventory, supplier, report, user-management, or stock-movement routes.
- Home page still uses a route closure.

Inventory route permissions:

- Admin and Staff can view inventory and add ingredients.
- Admin can edit and delete ingredients.
- Staff cannot edit or delete ingredients.

Stock route permissions:

- Admin and Staff can view stock history.
- Admin and Staff can record stock in.
- Admin and Staff can record stock out.

Alert and expiry permissions:

- Admin and Staff can view low-stock alerts.
- Admin can manage restock requests.
- Admin and Staff can view expiry tracking.
- Admin can remove expired stock.

Supplier permissions:

- Admin and Staff can view suppliers.
- Admin can add suppliers.
- Admin can edit suppliers.
- Supplier deletion is not implemented because it is not listed in the UAF table.

Report permissions:

- Admin and Staff can view reports.
- Admin can generate the summary report.
- Admin-only Excel upload/download for reports is confirmed but not implemented yet.

System permissions:

- Admin can manage system settings.
- Admin can create backup snapshots.

## 4. Layout And Styling

### Shared Layout

File:

- `resources/views/layouts/app.blade.php`

Current functions:

- Provides the base HTML document.
- Sets responsive viewport metadata.
- Uses dynamic page title with fallback to `Ting Hao`.
- Loads Google Fonts: Outfit and Manrope.
- Loads custom stylesheet from `public/css/tinghao.css`.
- Provides `@yield('content')` for page content.

### Custom CSS

File:

- `public/css/tinghao.css`

Current functions:

- Defines bakery-themed design tokens with CSS variables.
- Styles top navigation, buttons, hero, sections, product cards, contact area, footer, and login page.
- Includes responsive behavior for tablet and mobile widths.
- Includes a small hero entrance animation.

Current limitations:

- CSS includes `.staff-banner` and `.staff-box` styles, but no matching Blade section currently uses them.
- The site relies on external image URLs from Unsplash.
- No compiled Vite asset flow is used for the main custom stylesheet; it is loaded directly from `public/css`.

## 5. Database And Models

### User Model

File:

- `app/Models/User.php`

Current functions:

- Uses Laravel authenticatable user model.
- Supports factories and notifications.
- Fillable fields: `name`, `email`, `password`, `role`, `status`.
- Hidden fields: `password`, `remember_token`.
- Casts `email_verified_at` to datetime.
- Casts `password` using Laravel hashed cast.
- Provides role helpers for admin and staff.
- Provides active-account helper.

Current limitations:

- Role middleware is implemented for dashboard access, but module-level permissions still depend on future modules.
- No custom profile or staff fields exist.

### Existing Migrations

Files:

- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/0001_01_01_000001_create_cache_table.php`
- `database/migrations/0001_01_01_000002_create_jobs_table.php`
- `database/migrations/2026_05_21_000001_add_role_and_status_to_users_table.php`
- `database/migrations/2026_05_21_000002_create_categories_table.php`
- `database/migrations/2026_05_21_000003_create_ingredients_table.php`
- `database/migrations/2026_05_21_000004_create_stock_movements_table.php`
- `database/migrations/2026_05_21_000005_create_restock_requests_table.php`
- `database/migrations/2026_05_21_000006_create_suppliers_table.php`
- `database/migrations/2026_05_21_000007_add_supplier_id_to_ingredients_table.php`
- `database/migrations/2026_05_21_000008_create_system_settings_table.php`
- `database/migrations/2026_05_21_000009_create_backup_records_table.php`

Implemented database tables:

| Table | Purpose |
| --- | --- |
| `users` | Default Laravel user accounts |
| `password_reset_tokens` | Default password reset token storage |
| `sessions` | Database-backed session storage |
| `cache` | Database-backed cache storage |
| `cache_locks` | Cache lock storage |
| `jobs` | Queue job storage |
| `job_batches` | Batched queue job storage |
| `failed_jobs` | Failed queue job storage |
| `categories` | Ingredient category records |
| `ingredients` | Inventory ingredient records |
| `stock_movements` | Stock in/out quantity history |
| `restock_requests` | Low-stock restock workflow records |
| `suppliers` | Supplier contact and source records |
| `system_settings` | Configurable shop and system values |
| `backup_records` | Backup snapshot audit records |

Current database limitations:

- No purchase orders table.
- No sales table.
- No roles or permissions tables.

### Seeder

File:

- `database/seeders/DatabaseSeeder.php`

Current functions:

- Creates or updates the admin account.
- Creates or updates the staff account.
- Creates starter categories: Flour, Sugar, Dairy, Leavening, Packaging.
- Creates demo suppliers.
- Creates demo ingredients for presentation.
- Creates demo stock movement records.
- Creates demo restock requests.
- Creates demo system settings.
- Creates a demo backup snapshot.

Seed accounts:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@tinghao.com` | `password` |
| Staff | `staff@tinghao.com` | `password` |

Demo data coverage:

- Normal inventory items.
- Low-stock items.
- Expiring-soon items.
- Expired item.
- Supplier-linked ingredients.
- Stock in and stock out history.
- Open restock requests.

## 6. Frontend Build Setup

Files:

- `package.json`
- `vite.config.js`
- `resources/css/app.css`
- `resources/js/app.js`
- `resources/js/bootstrap.js`

Current functions:

- Vite is configured as the frontend build tool.
- Tailwind CSS 4 dependency is present.
- Axios dependency is present.
- Laravel Vite plugin is present.
- NPM scripts exist for `npm run dev` and `npm run build`.

Current limitations:

- The current visible pages mainly use `public/css/tinghao.css`.
- Tailwind/Vite assets are not the main styling path for the implemented pages yet.

## 6.1 Deployment Setup

Files:

- `Dockerfile`
- `.dockerignore`
- `render.yaml`
- `docker/nginx.conf.template`
- `docker/php.ini`
- `docker/supervisord.conf`
- `scripts/render-start.sh`
- `docs/render-deploy.md`

Current functions:

- Builds Vite frontend assets during Docker image build.
- Installs Composer production dependencies.
- Uses PHP 8.4 FPM because the current Composer lockfile requires PHP 8.4-compatible Symfony dependencies.
- Installs PostgreSQL PHP extensions for Supabase.
- Runs Nginx and PHP-FPM through Supervisor.
- Runs `php artisan migrate --force` during Render startup.
- Caches Laravel config, routes, and views during Render startup.
- Uses Render `$PORT` through the Nginx template.

## 7. Tests

Files:

- `tests/Feature/ExampleTest.php`
- `tests/Unit/ExampleTest.php`

Current functions:

- Feature test checks that `GET /` returns HTTP 200.
- Unit test checks that `true` is true.

Current limitations:

- No tests for the login page.
- No authentication tests.
- No database module tests.
- No inventory, supplier, reports, or role tests.

Verification note:

- `php artisan route:list` and `php artisan test` were attempted during this review, but both commands timed out in the current shell session. The route inventory above is based directly on `routes/web.php`.

## 8. Existing Documentation

Files:

- `readme.md`
- `docs/implementation-reference.md`
- `docs/LARAVEL_README.md`

Current documentation functions:

- `readme.md` describes the desired Ting Hao inventory management system vision.
- `docs/implementation-reference.md` records earlier implementation notes.
- `docs/LARAVEL_README.md` appears to preserve Laravel framework reference content.

Important distinction:

- The README describes planned project features.
- This inventory describes what is actually implemented now.

## 9. Planned But Not Yet Implemented

The following features are described or implied by the project idea, but are not built yet:

- User management for staff accounts.
- Admin Excel report upload/download.
- Purchase order management.
- Sales entry.
- Real product search.
- Real map/contact integration.
- Local image asset management.

## 10. Recommended Next Build Order

Suggested priority:

1. Add Admin Excel report upload/download.
2. Add staff user management for admins.
3. Add purchase order management if needed.
4. Replace placeholder public page content with real business data and local images.

## 11. Quick Development Notes

When adding backend features:

- Move page logic from route closures into controllers.
- Add request validation classes for form submissions.
- Add migrations before building CRUD screens.
- Keep admin-only and staff-allowed routes separate with middleware.

When adding frontend features:

- Reuse the existing visual direction in `public/css/tinghao.css`.
- Decide whether future styling should continue in `public/css/tinghao.css` or move into the Vite/Tailwind pipeline.
- Replace external Unsplash images with local files before production.
