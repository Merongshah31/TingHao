# Ting Hao Inventory Management System PRD

Last updated: 2026-07-19

2026-07-20 email safety requirement: Resend test mode must never send to a supplier address and must preserve the supplier as the intended business recipient for audit context.

2026-07-19 safety requirement: an autopilot restock mission cannot terminate before required duplicate, supplier, draft, and human-approval checkpoints are completed.

## 1. Product Summary

Ting Hao Inventory Management System is a Laravel-based web application for managing bakery ingredient inventory, stock movement, suppliers, purchase order receiving, supplier returns, expiry dates, reports, and system data.

The product is designed for internal Ting Hao staff and administrators. It should help the business know what ingredients are available, what needs restocking, what is expiring soon, and how stock changes over time.

2026-07-19 QA note: delivery-mode acceptance remains explicit. Demo Mark Sent is available only with real email disabled; Resend test-mode sending is available only with real email enabled and remains admin-controlled.

## 2. Product Goals

Primary goals:

- Centralize ingredient inventory records.
- Track stock in and stock out clearly.
- Alert users when ingredients are low stock.
- Track ingredient expiry dates.
- Manage supplier information.
- Record delivered goods accurately before changing inventory.
- Track accepted, damaged, returned, and short/missing supplier deliveries.
- Provide inventory and stock reports.
- Support admin and staff role-based access.
- Prepare the system for future POS integration.

Secondary goals:

- Provide a professional dashboard for daily operations.
- Provide a stable, grouped dashboard sidebar that works on desktop and collapses into a drawer on smaller screens.
- Support Supabase PostgreSQL as the database.
- Support Render deployment.
- Keep future API integration clean and secure.
- Keep stock prediction logic separate from Laravel UI/workflow code so planning can evolve independently.

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
- Real purchase order workflow with supplier email, supplier confirmation, detailed goods receiving, supplier returns, and close steps.
- Goods receiving workflow with accepted-only inventory updates, stock allocation, shortage alerts, and supplier return records.
- Purchase order demo workflow.
- Generated summary PDF report.
- Stock Memory Demo prototype.
- Help Center.
- System settings.
- Backup snapshot records.
- Documentation workflow requirement for all future system changes.
- Supabase PostgreSQL connection setup.
- Render Docker deployment setup.
- Demo seed/mock data.
- TingHao Agent foundation for Track 4 Autopilot procurement message parsing.
- Server-side Qwen integration with mock mode.
- Agent run and tool call audit logging.
- Autonomous Restock Engine that creates pending-approval purchase order drafts.
- Embedded TingHao Agent workflow cards and approval prompts across Dashboard, Low Stock, Inventory detail, Purchase Order, Supplier Email Draft, and Expiry pages.
- Separate FastAPI Stock Prediction Service MVP for rule-based Smart Stock Planner recommendations.
- Laravel Stock Planner integration that calls the FastAPI service, caches prediction results, shows Prediction View cards, Calendar View signals, and Dashboard prediction signals.
- Qwen explanation layer for Stock Planner prediction detail, limited to business-friendly reasoning after FastAPI returns a prediction.
- Stock Planner restock autopilot action that lets admin/staff turn eligible `add_stock_now` and `add_stock_soon` predictions into pending-approval PO drafts with audit logs.
- Agent Audit workflow visualizer with one connected map and one Selected Step Details panel. Template View shows procurement architecture; Live Run View selects procurement or expiry-loss nodes from the selected run and clearly marks human approval/review checkpoints.

Not yet implemented:

- Staff account management UI.
- Profile editing.
- Excel report upload/download.
- POS integration.
- JSON REST API.
- Product/recipe mapping for POS stock deduction.
- Real public search.
- Real map/contact integration.
- Production supplier communication delivery after reviewed drafts.
- Machine-learning stock forecasting model training.

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
- Local, low-cost stock prediction service MVP for Smart Stock Planner decisions.

### Out Of Scope For Current Version

- Full accounting system.
- Payment processing.
- Customer management.
- Full POS replacement.
- Multi-branch warehouse management.
- Barcode scanner hardware support.
- Mobile app.
- Public e-commerce checkout.
- Alibaba Cloud PAI, paid forecasting services, and automatic PO approval/supplier communication from stock predictions.
- Qwen-powered raw forecasting calculations.

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
| Purchase Order | View Purchase Orders | Yes | Yes |
| Purchase Order | Create/Edit/Delete Purchase Orders | Yes | No |
| Purchase Order | Generate/Regenerate/Approve Demo Supplier Email Draft | Yes | View own requested draft only |
| Purchase Order | Send Supplier Email | Yes | No |
| Purchase Order | Receive Goods And Record Allocation | Yes | Assigned PO only |
| Purchase Order | Close Purchase Order | Yes | No |
| Supplier Return | View Supplier Returns | Yes | Yes |
| Supplier Return | Update Return Status | Yes | No |
| Expiry Loss Prevention | View Recommendations | Yes | Yes |
| Expiry Loss Prevention | Run Scan / Review / Dismiss / Complete | Yes | No |
| Purchase Order Demo | View Demo Workflow | Yes | Yes |
| Purchase Order Demo | Create/Send/Confirm/Close Demo PO | Yes | No |
| Purchase Order Demo | Receive Demo Stock | Yes | Yes |
| Reports & Analytics | View Inventory Report | Yes | Yes |
| Reports & Analytics | Generate Reports | Yes | No |
| System Management | Backup System Data | Yes | No |
| System Management | Manage System Settings | Yes | No |
| TingHao Agent | View Agent Audit / Workflow Visualizer | Yes | Yes |
| TingHao Agent | View All Agent Runs | Yes | No |
| TingHao Agent | View Own Agent Runs | Yes | Yes |
| TingHao Agent | Approve/Reject Agent PO Draft | Yes | No |
| TingHao Agent | View Reasoning Activity | Yes | Yes |
| Dashboard Autopilot Actions | View PO/email approval cards | All records | Own requested PO/email records only |
| Stock Planner | View FastAPI-backed predictions | Yes | Yes |
| Stock Planner | Refresh ingredient prediction | Yes | Yes |
| Stock Planner | View/request Qwen explanation | Yes | Yes |
| Stock Planner | Request restock PO draft from eligible prediction | Yes | Yes |
| Stock Planner | Approve/reject prediction-created PO draft | Yes | No |

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
- Dashboard must show Today's Autopilot Actions as business decision cards without calling Qwen or exposing technical logs.
- Dashboard PO approval and supplier email draft cards must respect role visibility: admin sees the queue, staff sees only records tied to purchase orders they requested.
- Dashboard must not present staff with admin-only approval wording or the Human Review / Agent Approvals shortcut.
- Dashboard prediction cards must not display a zero suggested purchase quantity. Add-stock signals require a positive fallback, while non-purchase signals use plain business wording.
- Approved POs waiting for supplier communication must display `Needs email draft` rather than the generic `Approved` badge.
- Stock Planner cards, calendar advice, and prediction detail must never show a zero suggested purchase quantity for add-stock actions.
- Expired stock must be labelled for review/removal rather than use-before-expiry; usable below-minimum stock must remain an add-stock priority.
- Calendar cells must show at most two priority signals in this order: Add Stock Now, Add Stock Soon, expired-stock review, Use Before Expiry, Do Not Buy, Buy Less, then Monitor.
- The restock action must be hidden unless the normalized action is add-stock, suggested quantity is positive, and the existing workflow can select a supplier.
- Stock Planner detail must also require non-expired inventory and zero pending PO quantity before showing the active restock action.
- Expired detail pages must warn users to review/remove expired stock and link to Expiry. Pending-PO detail pages must explain the duplicate and link to the existing PO or PO list.
- Technical prediction payloads must remain under a collapsed `Technical Audit Details` section with explicit no-secrets/no-chain-of-thought wording.
- Demo-friendly name/unit corrections must remain display-only unless the record is confirmed seed-only data.

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
- Cached dashboard data must render consistently after refreshes and cache hits.
- Admin sees system controls.
- Staff sees operational controls only.
- Staff must not see Dashboard or Agent Audit links that open another user's PO approval or supplier email draft record.
- Staff pending PO cards must use Review wording; admin pending PO cards may use Approve wording.
- Shared paginated lists must keep navigation controls readable and compact.
- Demo guide and proof endpoints must help judges verify the Track 4 Autopilot Agent without exposing secrets.

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
- Admin and staff can ask TingHao Agent to plan restock for a low-stock item using existing ingredient and supplier data.
- Agent restock planning must create or prepare a purchase order draft and keep admin approval required.
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

### 8.10 Purchase Order Workflow

Requirements:

- Admin can create, edit, delete, and send supplier purchase orders.
- Admin and staff can view purchase orders.
- Purchase orders can include supplier, order dates, line items, quantity, unit price, subtotal, and notes.
- Admin can create a purchase order from low-stock items.
- Admin can confirm sent purchase orders before receiving; draft and approved POs must complete their email workflow first.
- Admin and assigned staff can receive goods against purchase order line items.
- Purchase order receiving must only be available when status is `confirmed` or `partially_received`.
- Non-receivable purchase orders should show a disabled receiving hint instead of linking to the receiving page.
- Receiving must record received, accepted, damaged, returned, shortage, quality status, notes, and location allocations.
- The receiving screen should remain compact and easy for staff to scan during delivery checks.
- Receiving quantity must equal accepted plus damaged plus shortage quantity.
- Accepted quantity must equal usable allocations to Store Room, Production Area, and Front Counter.
- Receiving validation errors should keep staff on the worksheet with the entered values visible for correction.
- The receiving worksheet should show the standard allocation locations even if seed data was not previously loaded.
- Only accepted quantity updates ingredient quantity and creates stock-in movement records.
- Damaged or returned quantity must create supplier return records.
- Shortage and damaged quantities must be visible on PO detail and dashboard alert counters.
- Admin can close a fully received purchase order.
- Staff cannot close purchase orders or update supplier return status.

Current demo workflow:

- Admin can create a presentation-ready PO demo.
- Admin can mark demo supplier email sent.
- Admin can generate a Qwen-backed supplier email draft after an agent-created PO is approved.
- Admin can regenerate a draft-status supplier email draft.
- Existing supplier email drafts are reused instead of calling Qwen again.
- If Qwen is unavailable outside explicit mock mode, no fake draft is saved and the PO remains approved.
- Admin can approve the draft and mark it sent for demo without sending real email.
- Admin can run expiry loss scans that calculate potential RM loss for ingredients expiring within 7 days.
- Expiry tracking should surface a simple expiry-loss prevention card with ingredient, days until expiry, RM loss, and recommended action.
- Admin can review, dismiss, or complete expiry loss recommendations.
- Staff can view expiry loss recommendations but cannot run scans or change statuses.
- Admin can confirm supplier response.
- Admin and staff can receive demo stock.
- Admin can close fully received demo POs.

Acceptance criteria:

- PO list and detail pages show current order state.
- Admin-only PO actions are protected.
- Staff receiving is limited to assigned/requested purchase orders.
- Direct receiving URL access for draft, pending approval, received, closed, or cancelled POs redirects back with a clear error.
- Inventory quantity changes only for accepted received stock.
- Supplier returns are visible to admin/staff and status updates are admin only.
- Demo PO receiving records stock-in when linked to an ingredient.
- Full GRN document generation remains future work until receiving document requirements are finalized.

### 8.11 Documentation Workflow

Requirements:

- Every system change must update related documentation before completion.
- `docs/CHANGELOG.md` must receive a new entry for each update.
- Database changes must update `docs/database.md`.
- UI changes must update `docs/ui-guide.md`.
- Route/controller changes must update `docs/backend-api.md`.
- Feature and permission scope changes must update this PRD.

Acceptance criteria:

- Future task summaries list documentation files updated.
- `AGENTS.md` remains the source instruction for AI coding agents.

### 8.12 TingHao Agent Foundation

Requirements:

- TingHao Agent should behave as an autopilot layer inside normal Ting Hao workflows, not as the main daily work page.
- `/agent` remains available as an audit console and hackathon judge view for missions, reasoning activity, tool calls, audit trail, and Qwen usage proof.
- Normal navigation should label the technical page as Agent Audit or Audit Console, not as the primary daily work module.
- Normal users should see clear business decisions: problem, recommendation, approve/edit/reject.
- Dashboard autopilot cards should show a visible status badge plus one main Review, Approve, or View action.
- Purchase order detail should be the main approval checkpoint and show the PO number, supplier, suggested item quantities, recommendation summary, why the order is suggested, and Approve/Edit/Reject controls before technical audit details.
- Supplier email draft detail should show supplier, subject, body, status, and the note that no real email is sent automatically because admin controls final action.
- `/agent` should not be the main message-entry workflow. Stock Planner, Low Stock, Dashboard Autopilot Actions, Purchase Orders, and Supplier Email Draft pages are the normal operator entry points.
- The legacy backend `POST /agent/run` route remains available for compatibility, tests, and direct audit workflows, but the `/agent` UI does not show the old procurement textarea or sample prompt buttons.
- Qwen parses ambiguous stock/supplier messages server-side only.
- Qwen requests should use compact prompts, purpose-specific max-token limits, shared low temperature, safe metadata, and parser caching.
- Qwen should not be called for dashboard cards, deterministic inventory calculations, basic supplier scoring, approval actions, or status updates.
- Expiry scans should batch Qwen recommendation text when multiple ingredients are scanned together.
- Mock mode supports demos without a real Qwen API key.
- Laravel internal tools perform inventory and supplier lookup against the existing database.
- Agent runs and tool calls are logged for auditability.
- Reasoning Activity must show safe explanations without raw chain-of-thought.
- Critical actions must require admin approval through human-in-the-loop guardrails.
- Technical reasoning activity and tool calls should be hidden behind Advanced Details on normal business workflow pages.
- Admin can view all runs; staff can view only their own runs.

Acceptance criteria:

- `/agent` loads for admin and staff.
- `/agent` shows proof links, status cards, the Autopilot Workflow Visualizer, and recent missions without the old procurement message form.
- Direct `POST /agent/run` submissions can still create `AgentRun` and `AgentToolCall` records for compatibility.
- Parsed intent, ingredients, urgency, supplier context, matched inventory, matched suppliers, and final summary are visible.
- The first MVP does not create purchase orders automatically.

### 8.13 Autonomous Restock Engine

Requirements:

- Parsed procurement messages can trigger restock planning.
- The system calculates recommended reorder quantities from parsed quantity or minimum stock/current quantity.
- The system ranks suppliers using linked supplier, contact availability, and parsed supplier hint.
- The system creates a purchase order draft with status `pending_approval`.
- The system creates an approval request with status `pending`.
- Admin can approve or reject the draft.
- Staff cannot approve or reject.

Acceptance criteria:

- Agent run detail links to the created purchase order draft.
- Purchase order detail shows agent reasoning and approval status.
- No real supplier email is sent by the agent in Phase 3; supplier email drafts remain approval-gated. Admins may explicitly send an approved draft through Resend only when real email delivery is enabled and configured.
- Phase 4 does not recommend expired ingredients and does not invent sales, POS, or recipe data.

### 8.14 Phase 5 Demo And Devpost Readiness

Requirements:

- Public `/demo` page explains how judges can test the project.
- `/health` returns safe deployment health metadata.
- `/agent/proof` returns safe Alibaba Cloud/Qwen proof metadata.
- Agent Audit clearly shows the Autopilot workflow from message parsing through audit logging.
- Agent run detail must read as an Autopilot Command Center, not only a technical log page.
- Dashboard shows Today's Autopilot Actions for low-stock restock planning, PO approval, supplier email draft review, and expiry-loss risk, while still linking to Agent Audit for audit.
- README and docs support Devpost submission and Alibaba Cloud proof recording.

Acceptance criteria:

- No API keys, database credentials, or secret environment values are shown.
- No real email is sent during the demo-safe supplier workflow.
- Existing business behavior remains unchanged.

### 8.15 Smart Stock Planner Prediction Integration

Requirements:

- Laravel must not perform raw forecasting calculations.
- Laravel collects compact ingredient, stock movement, expiry, pending PO, and risk-flag summaries and sends them to the FastAPI Stock Prediction Service.
- Laravel must cache prediction results and avoid repeated FastAPI calls while cache is valid.
- Admin and staff can force-refresh a prediction for one ingredient.
- Prediction View and Calendar View must reuse the same per-ingredient cached prediction result shape.
- Normal Stock Planner refreshes must not force FastAPI calls for every ingredient while cached results are valid.
- Dashboard must read cached prediction signals only and must not call FastAPI or Qwen.
- Dashboard should show cached important stock prediction signals for Add Stock Now, Add Stock Soon, Do Not Buy, and Use Before Expiry.
- Qwen must not be called for stock prediction in this phase.
- Qwen may be called only after a FastAPI prediction exists, and only to explain the prediction in simple business language.
- The system must not approve or send purchase orders automatically from prediction results.
- `add_stock_now` and `add_stock_soon` predictions may let admin/staff request a restock plan that creates a `pending_approval` PO draft.
- `do_not_buy`, `buy_less`, `monitor`, and `use_before_expiry` predictions must not create PO drafts.
- Duplicate open POs for the same ingredient must be prevented.
- Missing supplier or invalid suggested quantity must return clear guidance instead of creating a PO.
- If the prediction service is offline, Laravel must show a friendly unavailable message and keep the page usable.

Acceptance criteria:

- `/stock-planner` lists ingredients with prediction cards.
- `/stock-planner?view=calendar` shows date-based stock planning signals from the same prediction results.
- `/stock-planner/ingredient/{ingredient}/prediction` shows detail, compact input, and Advanced Details.
- `POST /stock-planner/ingredient/{ingredient}/refresh-prediction` forces a new service call for only the selected ingredient.
- `POST /stock-planner/ingredient/{ingredient}/plan-restock` creates an audited pending-approval PO draft only for eligible add-stock predictions.
- Dashboard prediction signals link to Stock Planner detail.
- Laravel sends summarized data only and does not expose API keys or raw tables.
- Legacy calendar/demo routes redirect into Stock Planner Calendar View rather than showing a separate static planning page.

### 8.16 Qwen Stock Prediction Explanation

Requirements:

- Qwen explains FastAPI prediction output; it must not calculate the prediction.
- Qwen receives compact prediction facts only.
- Qwen must return compact JSON with title, summary, business reason, recommended next step, warning, user-friendly action, and confidence label.
- Qwen prompts must require professional English only and must instruct the model not to expose chain-of-thought, not to invent missing values, not to invent customer behavior, competitors, sales, or demand, and not to suggest automatic purchasing.
- Qwen explanations must not include Malay or mixed Malay-English wording.
- Qwen explanations must not use `ASAP` unless explicit high urgency is provided in the facts.
- Qwen explanations must be cached by ingredient and prediction snapshot hash.
- The explicit explain action must be able to regenerate and replace older cached explanations for the same prediction snapshot.
- Stock Planner Prediction View cards, Calendar View, and Dashboard must not call Qwen.
- If Qwen is unavailable, Stock Planner detail must still show the FastAPI prediction.
- Advanced audit metadata can show model, mock mode, latency, token usage, and cache state, but never API keys or raw chain-of-thought.

Acceptance criteria:

- Stock Planner detail shows Qwen Explanation.
- `POST /stock-planner/ingredient/{ingredient}/explain` generates or regenerates an English-only explanation for the current prediction facts.
- Add Stock actions can create an existing-workflow PO draft with admin approval.
- Do Not Buy and Use Before Expiry recommendations do not create purchase orders or remove stock automatically.

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

Current demo/prototype tables:

- `purchase_order_demos`
- `purchase_order_demo_items`

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
- Dashboard metrics may be cached briefly to reduce repeated database aggregates.
- Long operational tables should use pagination.
- Production logging should avoid debug-level noise during normal page loads.
- Qwen API keys must stay server-side and never be exposed to Blade or frontend JavaScript.

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
- Purchase order workflow.
- Purchase order demo workflow.
- Generated summary PDF report.
- Stock Memory Demo prototype.
- Help Center.
- Documentation workflow.
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

Phase 11: TingHao Agent Autopilot

- Add human approval checkpoints.
- Convert accepted recommendations into draft purchase orders.
- Add supplier communication tools.
- Add real workflow automation only after audit and permission rules are clear.

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
- `docs/CHANGELOG.md`
- `docs/database.md`
- `docs/ui-guide.md`
- `docs/TODO.md`
- `AGENTS.md`
- `docs/supabase-setup.md`
- `docs/render-deploy.md`
# Maintenance note (2026-07-18)

The shared header must keep language selection visually separate from Admin/profile actions across desktop and mobile layouts. No product scope or permission behavior changed.

## Purchase Order Index Readability Requirement (2026-07-18)

- The Purchase Orders list must communicate the next valid business step from the current PO status.
- Terminal and rejected POs must not show receiving guidance.
- Confirmed and partially received POs must provide the valid receiving link; pending approval, approved, and sent POs must explain their approval, email-draft, or confirmation checkpoint.
- Source badges must distinguish Stock Prediction, TingHao Agent, and manual POs without dominating the row.
- Index actions must fit without horizontal overflow and must remain role-aware: staff cannot see admin-only approval, supplier email, confirmation, close, or edit actions.

## Create Purchase Order Advisory Suggestions (2026-07-18)

- Admins should receive editable suggestions for expected delivery date, item quantity, unit, and unit price while using the normal Create Purchase Order form.
- Delivery suggestions use supplier completion history, then an available supplier default, then two calendar days.
- Quantity suggestions use cached Stock Planner output only and must always be positive; no prediction API refresh occurs from the form.
- Price suggestions prefer same-supplier PO history, then any-supplier ingredient history, then ingredient cost price.
- Suggestions must never submit, create, approve, send, or confirm a PO. Human review and explicit Save remain mandatory.
- Staff must not access the create page or its suggestion endpoint.

## Purchase Order Detail Workflow Requirements (2026-07-18)

- Manual and Agent-generated POs must use different business timelines and must never display rejected as a normal future step.
- The detail page must show one clear Next step and only actions valid for the current status.
- Supplier confirmation requires the email/supplier-contact step to be marked sent first. Receiving requires confirmed or partially received status.
- Agent POs must label approval as Pending admin approval, Approved by admin, or Rejected by admin.
- Closed, rejected, and cancelled POs must not expose edit, confirm, receive, email approval, or close controls.
- Staff may view their own POs and perform existing authorized receiving work, but cannot approve, reject, generate/approve/mark-sent email drafts, confirm suppliers, or close POs.

## Goods Receiving Usability Requirements (2026-07-18)

- A clean row where received equals accepted and damage, return, and shortage are zero must display `Accepted / Good` by default.
- Quality status must visibly follow damage, return, or shortage quantities while remaining reviewable before recording.
- Quarantine / Damaged allocation must use neutral styling unless damage or return is entered.
- The receiving helper must distinguish the stock accounting equation from supplier return tracking.
- Long multi-item forms must provide Record Receiving at both the top and bottom without creating duplicate requests.
- These UI improvements must not change accepted-stock inventory updates or existing admin/assigned-staff permissions.

## Purchase Order Timeline Evidence Requirements (2026-07-18)

- Manual Email Sent must be completed only when a sent timestamp exists; a draft-only or unsent record must not appear completed.
- An equivalent reviewed supplier draft marked sent without real delivery evidence must use the label `Marked Sent`.
- A received PO must show Received completed and Closed as the current next action; a closed PO must show Closed completed.
- Manual approval metadata must use `Not applicable` where approval is not required.
- Agent and Stock Prediction approval labels, protected actions, and human approval requirements must remain unchanged.

## Agent Workflow Visualizer Requirements (2026-07-18)

- `/agent` must render one workflow map and one compact Selected Step Details panel, never a full repeated detail list for every node.
- Template View must remain a generic procurement system map and must not show selected-run metadata cards.
- Live Run View must show run number, status, owner, and created date, and select workflow nodes by run intent/input type.
- `expiry_loss_prevention` runs must show Trigger, Inventory Scan, Expiry Risk Calculated, RM Loss Calculated, Qwen Recommendation, Admin Review, and Audit Logged only.
- Status evidence must come from existing tool calls and linked records; missing evidence must be labelled `Not recorded` in details.
- Proof links, recent missions, technical audits, role scoping, no-secret rules, and no-raw-chain-of-thought rules remain mandatory.

## Responsive Demo Requirements (2026-07-18)

- Authenticated workflows must remain readable at 1366px+ desktop, 1024-1365px laptop, 768-1023px tablet, and 375-767px mobile widths without document-level horizontal scrolling.
- Dashboard navigation must remain fixed at desktop/laptop width and become an accessible overlay drawer at tablet/mobile widths; the menu itself may scroll vertically.
- Dashboard, Stock Planner, PO detail, Goods Receiving, Supplier Email Draft, and Agent Audit cards must reduce columns progressively without changing action visibility or workflow state.
- Wide data surfaces must scroll within their own container or use the existing responsive row treatment. Stock Planner's seven-day calendar may scroll within its calendar card on mobile.
- Main mobile actions should remain readable with approximately 44px touch height. Long titles, supplier content, email bodies, status labels, and audit text must wrap inside their cards.
- Responsive work must not introduce page-load Qwen calls, backend state changes, new routes, permission changes, or schema changes.

## Phase 1 Autopilot Requirements (2026-07-18)

- The production workflow is Observe -> Predict -> Decide -> Human Approve -> Act -> Verify -> Audit.
- Laravel owns scheduling, inventory/PO/email workflow, authorization, persistence, and audit. FastAPI remains the prediction source. Qwen remains limited to explanation, procurement parsing, and explicit supplier draft generation.
- Scheduled observation must deduplicate recent scans, use cached predictions, avoid Qwen, and never create buy actions for non-restock recommendations.
- Automatic PO drafting is opt-in, high-confidence only, duplicate-safe, and always creates `pending_approval`; it cannot approve or email a supplier.
- Supplier decisions must expose actual available price, delivery, receiving quality, and contact evidence. Missing evidence must be explicit, and admins must retain supplier/quantity edit control.
- Real Resend delivery is opt-in and explicit. It requires an approved PO, approved draft, admin action, safe acceptance evidence, duplicate-send protection, and no credential persistence or display.
- Resend test mode must send only to `RESEND_TEST_RECIPIENT` and use `onboarding@resend.dev`; production mode must use the linked supplier email and a verified `RESEND_FROM_ADDRESS`.
- Stock cannot change before validated goods receiving. Confirmation, accepted/damaged/returned/shortage totals, and close status must be auditable.
- Staff cannot approve/reject POs, edit/approve/send supplier email, confirm supplier, or close critical workflows. Admin remains the human approval authority.
- `/agent` and `/demo` must report real capability state as Available, Waiting, Completed, Not configured, or Failed without fake completion.

## Supplier Email Draft Deployment Compatibility (2026-07-19)

- Supplier-email draft editing must remain usable during a rolling deployment where optional delivery-audit columns have not reached the database yet.
- This compatibility behavior must not broaden admin-only permissions or claim that delivery evidence exists; applying the delivery-audit migration remains a release requirement.

## Bounded Agentic Restock Requirement (2026-07-19)

- Stock Planner restock missions must demonstrate an observe-decide-act loop where Qwen selects one allowed business action and Laravel remains the validating authority and tool executor.
- The loop must be limited to four decisions, use only supplied inventory/FastAPI/supplier/PO/expiry facts, and use deterministic safe fallback for invalid or unavailable model output.
- Eligible, duplicate-PO, and expired-item states must produce different tool paths. Only the eligible path may create one approval-gated PO draft.
- PO approval, supplier email, and inventory mutation remain outside model authority. The loop must stop at the existing human approval checkpoint.
- Agent Audit must present safe summaries of observations, actions, tool results, confidence, and stop reason without raw chain-of-thought, raw model output, or credentials.
# Agent Audit Visualizer Requirement (2026-07-19)

- The judge/admin Agent Console must present one non-duplicated, selected-run decision trail rather than separate template and live detail lists.
- The visible proof must cover trigger, observation, prediction, decision, human approval, action, verification, and audit state using only persisted evidence.
- Restock and expiry missions may use different underlying records; unused procurement outcomes on expiry missions must be labelled not used.
- Human approval state and reviewer evidence must remain prominent. Staff remain limited to their own run evidence.
- Raw chain-of-thought, secrets, and raw payloads are excluded from the default visualizer.

## Agent Audit Readability Requirement (2026-07-19)

- The selected live mission must be the primary Agent Console evidence, with static architecture hidden by default.
- The business path must contain no more than seven milestones and one selected detail panel.
- Actor labels must distinguish Qwen Decision, FastAPI Prediction, Laravel Tool, Human Approval, and System Audit without claiming actors absent from the recorded run.
- Technical tool-call granularity remains available in a collapsed audit disclosure.
