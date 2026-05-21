# Ting Hao Current Function Inventory

Last reviewed: 2026-05-21

This document identifies what the project already has now, based on the actual Laravel files in the repository. It separates implemented functions from planned features so the next development work can be chosen clearly.

## 1. Project Status Summary

The project is currently a Laravel 13 application with:

- A public landing page for Ting Hao.
- A visual admin/staff login page.
- A shared Blade layout.
- A custom CSS theme for the public and login pages.
- Default Laravel database tables for users, sessions, cache, and queues.
- Basic starter tests.

The inventory management system modules described in the README are still mostly planned. Real authentication, admin dashboards, stock management, suppliers, reports, and role permissions are not implemented yet.

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

Current limitations:

- Form action is `#`, so it does not submit to a real login endpoint.
- No authentication controller exists yet.
- No login validation exists yet.
- No session login/logout flow exists yet.
- Forgot password is visual only.
- No role handling for admin or staff exists yet.

## 3. Routing

Route file:

- `routes/web.php`

Implemented routes:

| Method | URI | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/` | `home` | Show public Ting Hao landing page |
| GET | `/login` | `login` | Show visual staff/admin login page |

Current routing limitations:

- No POST login route.
- No logout route.
- No dashboard routes.
- No inventory, supplier, report, user-management, or stock-movement routes.
- Routes currently use closures/views instead of controllers.

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
- Fillable fields: `name`, `email`, `password`.
- Hidden fields: `password`, `remember_token`.
- Casts `email_verified_at` to datetime.
- Casts `password` using Laravel hashed cast.

Current limitations:

- No role field exists.
- No staff/admin permission logic exists.
- No custom profile or staff fields exist.

### Existing Migrations

Files:

- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/0001_01_01_000001_create_cache_table.php`
- `database/migrations/0001_01_01_000002_create_jobs_table.php`

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

Current database limitations:

- No products table.
- No inventory table.
- No stock movements table.
- No suppliers table.
- No purchase orders table.
- No sales table.
- No roles or permissions tables.

### Seeder

File:

- `database/seeders/DatabaseSeeder.php`

Current functions:

- Creates one default test user through the user factory.
- User name: `Test User`
- User email: `test@example.com`

Current limitations:

- No known default password is documented in the project files.
- No admin or staff seed users are defined.
- No inventory sample data exists.

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

- Real admin/staff authentication.
- Admin dashboard.
- Staff dashboard.
- Role-based access control.
- User management for staff accounts.
- Inventory/product CRUD.
- Stock-in and stock-out recording.
- Low stock alerts.
- Supplier management.
- Purchase order management.
- Sales entry.
- Reports and analytics.
- Backup/settings/system control pages.
- Real product search.
- Real map/contact integration.
- Local image asset management.

## 10. Recommended Next Build Order

Suggested priority:

1. Implement real authentication: POST login, logout, session handling, and protected routes.
2. Add roles: admin and staff.
3. Create dashboard routes and layouts for authenticated users.
4. Design database schema for products, suppliers, stock movements, and sales.
5. Build inventory CRUD first because other modules depend on it.
6. Add stock-in and stock-out workflows.
7. Add low-stock reports.
8. Add supplier management.
9. Add staff user management for admins.
10. Replace placeholder public page content with real business data and local images.

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
