# Ting Hao Laravel Implementation Reference

This document records what has been implemented so far in the Ting Hao project for future maintenance and extension.

## 1. Current Build Scope

The project currently includes:
- A public landing page aligned with the provided design direction.
- A dedicated login UI page for admin or staff access entry.
- Shared layout and custom stylesheet.
- Basic route wiring for page navigation.

Not yet implemented:
- Real authentication logic and user session handling.
- Inventory, supplier, reports, and role authorization modules.
- Database-backed admin or staff dashboards.

## 2. Technology and Structure

Framework:
- Laravel 13

Main files involved:
- routes/web.php
- resources/views/layouts/app.blade.php
- resources/views/home.blade.php
- resources/views/auth/login.blade.php
- public/css/tinghao.css

## 3. Route Map

Current routes:
- GET / -> home view
- GET /login -> auth login view

Definitions live in routes/web.php.

## 4. Page Details

### 4.1 Home Page

File: resources/views/home.blade.php

Sections implemented:
- Sticky top navigation
- Hero section with overlay image and call-to-action buttons
- Mission and statistics section
- Curated products card grid
- Contact and map placeholder section
- Footer links

Notes:
- Hero and some cards use external Unsplash image sources.
- Buttons currently point to anchors and login route.

### 4.2 Login Page

File: resources/views/auth/login.blade.php

Sections implemented:
- Left-side promotional image panel
- Right-side login form panel
- Email and password fields
- Remember me and forgot password placeholders
- Footer meta links for policy and support

Notes:
- Form is visual at this stage and does not submit to a real authentication endpoint yet.

## 5. Shared Layout and Styling

Layout file:
- resources/views/layouts/app.blade.php

Purpose:
- Central HTML shell
- Shared font loading
- Shared CSS inclusion through public/css/tinghao.css

Stylesheet file:
- public/css/tinghao.css

Style system includes:
- Bakery-themed color tokens using CSS variables
- Responsive breakpoints for mobile and tablet behavior
- Hero, card, contact, footer, and login components
- Basic entrance animation for hero content

## 6. Unsplash Image Link Pattern

Important:
Unsplash photo page URLs are not direct image files.

Use this conversion rule:
- Photo page: https://unsplash.com/photos/<photo_id>
- Direct image: https://unsplash.com/photos/<photo_id>/download?force=true&w=1800

Example used in project:
- https://unsplash.com/photos/X3XSSryTj3k/download?force=true&w=1800

Recommendation:
- For production reliability and speed, move external images into local assets under public/images and reference them locally.

## 7. Run and Validate

Setup and run:
1. composer install
2. php artisan serve

Open:
- http://127.0.0.1:8000
- http://127.0.0.1:8000/login

Basic validation command:
- php artisan view:cache

## 8. Suggested Next Implementation Steps

Priority order:
1. Install Laravel authentication scaffolding (Breeze or custom auth).
2. Add roles and permissions for admin and staff.
3. Create database schema for products, stock movement, suppliers, and users.
4. Build inventory CRUD screens and stock in or stock out flows.
5. Add report pages for low stock and sales summaries.
6. Replace placeholder links and map box with real integrations.

## 9. Maintenance Notes

When editing UI:
- Keep structure changes in Blade files under resources/views.
- Keep visual tokens and responsive behavior centralized in public/css/tinghao.css.

When adding backend features:
- Introduce controllers and request validation classes in app.
- Move route closures to controllers as modules become dynamic.

This file should be updated whenever new modules are added or architecture decisions change.
