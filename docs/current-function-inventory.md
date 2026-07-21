# Ting Hao Current Function Inventory

Last reviewed: 2026-07-19

This document identifies what the project already has now, based on the actual Laravel files in the repository. It separates implemented functions from planned features so the next development work can be chosen clearly.

2026-07-19 verification note: supplier email feature tests explicitly isolate demo Mark Sent and Resend test mode through Laravel config overrides; no production workflow or permission behavior changed.

2026-07-20 Resend note: test-mode delivery separates the intended supplier recipient from the actual configured test recipient; production delivery uses the linked supplier email.

2026-07-19 restock safety note: Laravel validates bounded Qwen stop actions against mission state and records rejected premature stops without bypassing duplicate, expiry, supplier, or human-approval guardrails.

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
- Real purchase order list, create, view, edit, delete, supplier email, supplier confirmation, detailed goods receiving, stock allocation, supplier return, and close workflow.
- Goods receiving page uses a worksheet UI with item metrics, receiving quantity groups, and stock allocation cards.
- Goods receiving defaults normal accepted stock allocation to Store Room, auto-restores standard allocation locations when missing, and returns allocation mismatches as form validation errors.
- Presentation-ready purchase order demo workflow with supplier confirmation, stock receiving, and closing steps.
- Reports dashboard.
- Inventory, stock movement, low-stock, and expiry report pages.
- Admin-only generated summary report.
- Admin-only generated summary PDF report.
- Stock Memory Demo prototype page.
- Help Center page with localized guidance.
- System settings page.
- Backup snapshot records.
- Required documentation workflow for all future system changes.
- Dashboard summary metrics are aggregated and cached briefly for performance, with recent movement rows cached as scalar arrays for stable Blade rendering.
- Report, low-stock, and expiry list pages use pagination to avoid loading huge datasets.
- Paginated pages use scoped pagination CSS so Laravel paginator arrows render at normal icon size.
- Local-only performance logging records route name, query count, and response time.
- TingHao Agent Audit Console for Track 4 Autopilot procurement message parsing.
- TingHao Agent embedded autopilot actions on Dashboard, Low Stock, Inventory detail, Purchase Order detail, Supplier Email Draft, and Expiry pages.
- Server-side Qwen client with mock mode fallback.
- Agent run and tool call audit logging.
- Autonomous Restock Engine that plans reorder quantities, ranks suppliers, creates pending-approval PO drafts, and logs approval requests.
- Supplier Email Draft workflow that generates or regenerates Qwen-backed drafts for approved POs, reuses existing drafts to avoid duplicate Qwen calls, lets admins approve drafts, and marks them sent for demo without real email delivery.
- Expiry Loss Prevention Agent that scans ingredients expiring within 7 days, calculates potential RM loss, and stores Qwen-backed usage recommendations.
- Reasoning Activity timeline that explains Observe, Understand, Plan, Tool Action, Tool Result, Decision, Risk Check, Human Checkpoint, and Final Summary steps without exposing raw chain-of-thought.
- Phase 5 demo readiness pages and endpoints: `/demo`, `/health`, and `/agent/proof`.
- Devpost, Alibaba Cloud deployment proof, Qwen usage, architecture, and demo script documentation.
- Autopilot Command Center UI for agent missions, including mission summary, next best action, business impact, safety guardrails, grouped reasoning activity, and workflow stepper.
- Agent Audit `/agent` includes one connected Autopilot Workflow map and one Selected Step Details panel. Template View shows generic procurement architecture; Live Run View switches between procurement and expiry-loss nodes from the selected AgentRun, with status colors and human approval checkpoints.
- Separate FastAPI Stock Prediction Service MVP in `prediction-service/` with rule-based stock action recommendations for Smart Stock Planner.
- Laravel Stock Planner integration that calls the FastAPI service with compact per-ingredient summaries, caches results, supports force refresh, shows Prediction View cards, Calendar View signals, and cached Dashboard prediction signals.
- Qwen stock prediction explanation layer that explains FastAPI prediction results on Stock Planner detail in professional English without recalculating forecasts or receiving full stock history.
- Stock Prediction Restock Autopilot workflow that turns eligible `add_stock_now` and `add_stock_soon` predictions into pending-approval PO drafts through existing agent audit and human approval records.
- A shared Blade layout.
- Fixed dark-green dashboard sidebar with compact grouped navigation, clickable Autopilot Actions item, desktop-width consistency, vertical-only menu scrolling, and mobile drawer behavior.
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
- Role handling covers dashboard routing plus the implemented module-level rules documented in the permissions section. Future modules still need explicit permission checks as they are added.

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
- Shows Today's Autopilot Actions for low-stock restock planning, PO approval, supplier email draft review, and expiry-loss risk.
- Shows Stock Prediction-created PO drafts as ingredient restock plans waiting for approval in Today's Autopilot Actions.
- Shows visible status badges and one clear Review, Approve, or View action on each Today's Autopilot Actions card.
- Approved purchase orders without a supplier email draft use the clear `Needs email draft` status badge.
- Staff Today's Autopilot Actions only show PO approval and supplier email draft links tied to purchase orders requested by that staff user; admin sees the global approval queue.
- Staff pending PO Autopilot cards use Review wording, while admin pending PO cards use approval wording.
- Shows TingHao Agent audit/proof entry points without exposing the old procurement message-entry form on the `/agent` page.
- Sidebar daily navigation points users to Dashboard Autopilot Actions and moves the technical agent page under Audit & Demo as Agent Audit.
- Shows purchase order approval entry points for admin review.
- Shows Demo Guide, pending agent approval count, pending supplier email draft count, open supplier return count, and receiving discrepancy entry points.
- Shows the Human Review / Agent Approvals shortcut only to admin users.

Current limitations:

- Analytics are generated from current inventory and stock movement records only.
- No sales/POS analytics are connected yet.
- No chart JavaScript library is used; visualizations are CSS-based.

### TingHao Agent Audit Console

Routes:

- `GET /agent`
- `POST /agent/run`
- `GET /agent/runs/{agentRun}`

Current functions:

- Shows the TingHao Agent Audit Console for the Qwen Cloud Hackathon Track 4 Autopilot Agent foundation.
- Lets admin and staff paste messy stock or supplier messages.
- Includes sample procurement prompts for sugar, flour/milk, and supplier delivery confirmation.
- Uses Qwen from Laravel server-side code only when configured.
- Falls back to mock mode when `QWEN_MOCK_MODE=true` or no Qwen API key is available.
- Shows Qwen mode as "Live Qwen mode" or "Mock demo mode", model name, and server-side configuration without exposing the API key.
- Uses purpose-specific Qwen max-token settings and shared temperature.
- Caches identical procurement parse input by normalized hash for the configured cache window.
- Batches expiry recommendation text generation for all expiring ingredients in one scan.
- Parses procurement intent, ingredient hints, supplier name, urgency, language, deadline, and summary.
- Looks up matching ingredients from the existing database.
- Looks up matching suppliers from parsed supplier names or matched ingredient supplier links.
- Calculates recommended restock quantities.
- Ranks suppliers using linked supplier and contact metadata.
- Creates pending-approval purchase order drafts when restock planning succeeds.
- Shows agent-created PO business summaries with suggested item quantities before advanced audit details.
- Creates approval request records for human review.
- Generates supplier email drafts after the linked PO is approved.
- Agent mission detail uses the canonical `purchase-orders.generate-email-draft` route for supplier email draft generation.
- Logs supplier email context building, Qwen draft call, draft saving, waiting-for-approval, draft approval, and demo mark-sent tool calls.
- Scans expiring inventory for RM loss prevention and logs scan, calculation, recommendation, and save tool calls.
- Stores `agent_runs`, `agent_tool_calls`, and `agent_reasoning_steps` records.
- Shows a visible timeline: Message Parsed -> Inventory Checked -> Restock Planned -> Supplier Ranked -> PO Drafted -> Waiting for Admin Approval.
- Shows an eight-step demo flow: Message Parsed -> Inventory Checked -> Supplier Ranked -> PO Drafted -> Admin Approval -> Email Drafted -> Marked Sent -> Audit Logged.
- Agent run detail reads as an Autopilot Command Center with mission header, summary, next best action, business impact, safety guardrails, grouped Reasoning Activity, and tool-call details.
- Links to Demo Guide and Proof JSON for judge verification.
- Agent Audit summary cards scope pending PO approvals, email drafts waiting approval, and recent missions to the current staff user; admin cards remain global.
- Admin can view all agent runs.
- Staff can view only their own agent runs.

Current limitations:

- Creates purchase order drafts and supplier email drafts only; the agent loop does not send real supplier messages.
- Does not send WhatsApp messages or live supplier emails.
- Human-in-the-loop approval is implemented for pending agent PO drafts.
- Mock parsing is deterministic and intentionally lightweight for demos.

### Stock Prediction Service MVP

Routes:

- FastAPI `GET /health`
- FastAPI `POST /predict-stock-action`
- Laravel `GET /stock-planner`
- Laravel `GET /stock-planner?view=cards`
- Laravel `GET /stock-planner?view=calendar`
- Laravel `GET /stock-planner/ingredient/{ingredient}/prediction`
- Laravel `POST /stock-planner/ingredient/{ingredient}/refresh-prediction`
- Laravel `POST /stock-planner/ingredient/{ingredient}/explain`
- Laravel `POST /stock-planner/ingredient/{ingredient}/plan-restock`
- Legacy redirect `GET /stock-memory-demo`
- Legacy redirect `GET /calendar-demo`

Current functions:

- Runs as a separate local FastAPI service in `prediction-service/`.
- Laravel reads stock prediction configuration from `config/stock_prediction.php` and `.env`.
- Laravel builds compact inputs with ingredient name, current quantity, unit, minimum stock, 7/14/30-day stock-out totals, expiry days, pending PO quantity, supplier lead time, weekend risk, and festival risk.
- Laravel does not send full stock movement history or full database tables to the prediction service.
- Laravel caches prediction responses for `STOCK_PREDICTION_CACHE_MINUTES`; normal page refreshes reuse cached predictions, and the refresh button forces a new prediction call for one selected ingredient only.
- Stock Planner Prediction View cards and detail pages show predicted action, estimated stockout days, suggested quantity, risk, confidence, and reason badges.
- Stock Planner Calendar View converts the same cached/generated prediction result shape into date-based signals such as Add Stock, Add Soon, Buy Less, Do Not Buy, and Use Before Expiry.
- Calendar View date advice shows selected-date action, affected ingredient, current/minimum stock, stockout estimate, suggested quantity, risk, reason badges, and next action controls.
- Stock Planner detail shows a Qwen Explanation section with English-only title, summary, business reason, warning, recommended next step, user-friendly action, and confidence label.
- Stock Planner detail can create a restock autopilot workflow only when FastAPI recommends `add_stock_now` or `add_stock_soon`.
- Restock planning creates an `agent_runs` record, safe reasoning steps, tool-call audit entries, a `pending_approval` purchase order draft, one PO item, and an `approval_requests` record when supplier and quantity are valid.
- The workflow blocks `do_not_buy`, `buy_less`, `monitor`, and `use_before_expiry` from creating POs and shows plain business advice instead.
- Duplicate open POs for the same ingredient are prevented and the user is redirected to the existing PO.
- Raw prediction response and Qwen audit metadata are hidden under Advanced Details on the detail page.
- Dashboard shows up to three cached important prediction signals and links to Stock Planner detail.
- Dashboard add-stock signals replace zero/null suggested quantities with a positive minimum-stock fallback; do-not-buy, buy-less, monitor, and expiry-use signals show business wording instead of a meaningless zero quantity.
- Dashboard uses cached prediction signals only and does not call the FastAPI prediction service.
- Qwen explanation is cached by ingredient and prediction snapshot hash for page display, while the explicit explanation action regenerates and replaces the cached English explanation.
- Dashboard, Prediction View cards, and Calendar View do not call Qwen.
- FastAPI and Laravel enforce a positive fallback quantity for add-stock actions using the two-times-minimum target when the calculated suggestion is missing, invalid, or zero.
- Expired stock is presented as `Review Expired Stock`; usable below-minimum stock takes precedence over lower-priority expiry advice; high stock near expiry remains `Do Not Buy`.
- Prediction cards and advice panels replace zero quantities on non-purchase actions with plain business guidance.
- Calendar day cells show at most two highest-priority signals; the Date Advice panel shows at most four compact secondary signals.
- Restock controls require an add-stock action, positive quantity, and a supplier available through the existing direct/category selection workflow.
- Prediction detail rechecks full current input after cache retrieval. Expired items and ingredients with pending PO quantity cannot show an active restock action.
- Pending PO guidance links to the latest active purchase order for that ingredient when available.
- Technical Audit Details remains collapsed by default and exposes only sanitized API response/audit metadata, never API keys or raw chain-of-thought.
- Stock Planner display aliases present `botol` as `bottle` and correct the identified demo names without changing stored records.
- Qwen stock explanation prompt and fallback rules reject Malay/mixed-language wording, do not use markdown, do not expose chain-of-thought, and use only provided FastAPI prediction facts.
- Local environment logs record Stock Planner prediction cache hits, misses, and forced refreshes without exposing secrets.
- The old static stock memory calendar is no longer a user-facing module and redirects to Stock Planner Calendar View.
- Predicts stock planning actions from inventory quantities, usage totals, expiry days, pending PO quantity, supplier lead time, weekend risk, and festival risk.
- Returns recommended actions: `add_stock_now`, `add_stock_soon`, `monitor`, `buy_less`, `do_not_buy`, and `use_before_expiry`.
- Calculates average daily usage from 7-day, 14-day, and 30-day stock-out totals.
- Prefers 14-day usage when available, then 7-day usage, then 30-day usage.
- Returns estimated stockout days, suggested quantity, risk level, confidence, reason codes, and calculation summary.
- Handles missing or zero usage data by returning `monitor` with `insufficient_usage_data` unless a real minimum-stock threshold is breached.

Current limitations:

- Dashboard signals require cached predictions from Stock Planner before they appear.
- Calendar View currently prioritizes the first 50 ingredients for signal generation.
- Does not approve, send, or receive purchase orders automatically; eligible predictions only prepare drafts for admin review.
- Does not create POs when supplier data is missing or suggested quantity is invalid.
- Does not use Qwen for raw forecasting calculations and does not call Alibaba Cloud PAI.
- Does not train a machine learning model.
- Qwen explanations use cache only; no long-term explanation audit table exists yet.
- Intended for local MVP use before production authentication and deployment decisions.

## 3. Routing

Route file:

- `routes/web.php`

Implemented routes:

| Method | URI | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/` | `home` | Show public Ting Hao landing page |
| GET | `/health` | `health` | Lightweight health check response |
| GET | `/agent/proof` | `agent.proof` | Safe Alibaba Cloud/Qwen proof JSON |
| GET | `/demo` | `demo` | Judge-friendly demo guide |
| GET | `/login` | `login` | Show visual staff/admin login page |
| POST | `/login` | `login.store` | Authenticate user credentials |
| GET | `/dashboard` | `dashboard` | Redirect user to role dashboard |
| GET | `/admin/dashboard` | `admin.dashboard` | Show protected admin dashboard |
| GET | `/staff/dashboard` | `staff.dashboard` | Show protected staff dashboard |
| GET | `/agent` | `agent.index` | Show Agent Audit Console, Autopilot Workflow Visualizer, proof/status cards, and recent agent runs |
| POST | `/agent/run` | `agent.run` | Preserved backend route to run parser and lookup tools for a procurement message |
| GET | `/agent/runs/{agentRun}` | `agent.runs.show` | Show parsed result, matched context, and tool timeline |
| GET | `/agent/expiry-loss` | `agent.expiry-loss` | Show expiry loss recommendations and RM impact |
| POST | `/agent/expiry-loss/scan` | `agent.expiry-loss.scan` | Run expiry loss prevention scan |
| GET | `/inventory` | `inventory.index` | Show searchable inventory list |
| GET | `/inventory/create` | `inventory.create` | Show add ingredient form |
| POST | `/inventory` | `inventory.store` | Store new ingredient |
| GET | `/inventory/{ingredient}` | `inventory.show` | Show ingredient detail |
| GET | `/inventory/{ingredient}/edit` | `inventory.edit` | Show edit ingredient form |
| PUT | `/inventory/{ingredient}` | `inventory.update` | Update ingredient |
| DELETE | `/inventory/{ingredient}` | `inventory.destroy` | Delete ingredient |
| GET | `/stock/history` | `stock.index` | Show stock movement history |
| GET | `/stock-planner` | `stock-planner.index` | Show Stock Planner Prediction View or Calendar View |
| GET | `/stock-planner/ingredient/{ingredient}/prediction` | `stock-planner.prediction` | Show one ingredient stock prediction detail |
| POST | `/stock-planner/ingredient/{ingredient}/refresh-prediction` | `stock-planner.refresh-prediction` | Refresh one ingredient FastAPI prediction |
| POST | `/stock-planner/ingredient/{ingredient}/explain` | `stock-planner.explain` | Generate or regenerate English-only Qwen explanation |
| POST | `/stock-planner/ingredient/{ingredient}/plan-restock` | `stock-planner.plan-restock` | Create pending-approval PO draft from eligible add-stock prediction |
| GET | `/stock-memory-demo` | `stock-memory.demo` | Redirect to Stock Planner Calendar View |
| GET | `/help-center` | `help-center.index` | Show help center and workflow guidance |
| GET | `/inventory/{ingredient}/stock/{type}` | `stock.create` | Show stock-in or stock-out form |
| POST | `/inventory/{ingredient}/stock/{type}` | `stock.store` | Record stock-in or stock-out |
| GET | `/alerts/low-stock` | `alerts.low-stock` | Show low-stock alerts |
| POST | `/alerts/low-stock/{ingredient}/restock` | `alerts.restock.request` | Create restock request |
| POST | `/alerts/low-stock/{ingredient}/agent-plan` | `alerts.restock.agent-plan` | Ask TingHao Agent to prepare a pending-approval PO draft from a low-stock ingredient |
| PATCH | `/alerts/restock/{restockRequest}` | `alerts.restock.update` | Update restock status |
| GET | `/purchase-orders` | `purchase-orders.index` | Show purchase order list |
| GET | `/purchase-orders/create/from-low-stock` | `purchase-orders.create-from-low-stock` | Create purchase order from low-stock items |
| GET | `/purchase-orders/create` | `purchase-orders.create` | Show purchase order form |
| POST | `/purchase-orders` | `purchase-orders.store` | Store purchase order |
| GET | `/purchase-orders/{purchaseOrder}` | `purchase-orders.show` | Show purchase order detail |
| GET | `/purchase-orders/{purchaseOrder}/edit` | `purchase-orders.edit` | Show purchase order edit form |
| PUT | `/purchase-orders/{purchaseOrder}` | `purchase-orders.update` | Update purchase order |
| DELETE | `/purchase-orders/{purchaseOrder}` | `purchase-orders.destroy` | Delete purchase order |
| POST | `/purchase-orders/{purchaseOrder}/send-email` | `purchase-orders.send-email` | Send purchase order supplier email |
| POST | `/purchase-orders/{purchaseOrder}/generate-email-draft` | `purchase-orders.generate-email-draft` | Generate supplier email draft for an approved purchase order |
| POST | `/purchase-orders/{purchaseOrder}/email-draft` | `purchase-orders.email-draft` | Compatibility alias for supplier email draft generation |
| POST | `/purchase-orders/{purchaseOrder}/approve` | `purchase-orders.approve` | Approve pending agent purchase order draft |
| POST | `/purchase-orders/{purchaseOrder}/reject` | `purchase-orders.reject` | Reject pending agent purchase order draft |
| POST | `/purchase-orders/{purchaseOrder}/confirm` | `purchase-orders.confirm` | Confirm a sent purchase order before receiving |
| GET | `/purchase-orders/{purchaseOrder}/receive` | `purchase-orders.receive-form` | Show detailed goods receiving and allocation form only for confirmed or partially received POs |
| POST | `/purchase-orders/{purchaseOrder}/receive` | `purchase-orders.receive` | Record accepted, damaged, returned, shortage, notes, and allocation quantities; validation mismatches return to the form |
| POST | `/purchase-orders/{purchaseOrder}/close` | `purchase-orders.close` | Close received purchase order |
| GET | `/supplier-returns` | `supplier-returns.index` | List supplier return records |
| GET | `/supplier-returns/{supplierReturn}` | `supplier-returns.show` | Show supplier return detail |
| PATCH | `/supplier-returns/{supplierReturn}` | `supplier-returns.update` | Admin updates supplier return status/reason |
| GET | `/supplier-email-drafts/{supplierEmailDraft}` | `supplier-email-drafts.show` | Show generated supplier email draft |
| POST | `/supplier-email-drafts/{supplierEmailDraft}/approve` | `supplier-email-drafts.approve` | Approve supplier email draft |
| POST | `/supplier-email-drafts/{supplierEmailDraft}/mark-sent` | `supplier-email-drafts.mark-sent` | Mark supplier email draft sent for demo when real email is disabled |
| POST | `/supplier-email-drafts/{supplierEmailDraft}/send-resend` | `supplier-email-drafts.send-resend` | Admin sends approved draft through Resend after explicit confirmation |
| POST | `/supplier-email-drafts/{supplierEmailDraft}/regenerate` | `supplier-email-drafts.regenerate` | Regenerate a draft-status supplier email draft |
| GET | `/purchase-order-demo` | `po-demo.index` | Show purchase order demo list |
| GET | `/purchase-order-demo/create` | `po-demo.create` | Show demo purchase order form |
| POST | `/purchase-order-demo` | `po-demo.store` | Store demo purchase order |
| GET | `/purchase-order-demo/{po}` | `po-demo.show` | Show demo purchase order detail |
| POST | `/purchase-order-demo/{po}/send-email-demo` | `po-demo.send-email` | Mark demo email as sent |
| POST | `/purchase-order-demo/{po}/confirm-demo` | `po-demo.confirm` | Confirm demo supplier response |
| POST | `/purchase-order-demo/{po}/receive-demo` | `po-demo.receive` | Receive demo purchase order stock |
| POST | `/purchase-order-demo/{po}/close-demo` | `po-demo.close` | Close demo purchase order |
| GET | `/expiry` | `expiry.index` | Show expiry tracking |
| POST | `/expiry/{ingredient}/remove` | `expiry.remove` | Remove expired stock |
| GET | `/expiry-loss-recommendations/{expiryLossRecommendation}` | `expiry-loss-recommendations.show` | Show expiry loss recommendation |
| POST | `/expiry-loss-recommendations/{expiryLossRecommendation}/review` | `expiry-loss-recommendations.review` | Mark expiry loss recommendation reviewed |
| POST | `/expiry-loss-recommendations/{expiryLossRecommendation}/dismiss` | `expiry-loss-recommendations.dismiss` | Dismiss expiry loss recommendation |
| POST | `/expiry-loss-recommendations/{expiryLossRecommendation}/complete` | `expiry-loss-recommendations.complete` | Mark expiry loss recommendation completed |
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
| GET | `/reports/generated-summary/pdf` | `reports.generated-summary.pdf` | Download admin generated summary PDF |
| GET | `/system/settings` | `system.settings` | Show system settings |
| PUT | `/system/settings` | `system.settings.update` | Update system settings |
| GET | `/system/backups` | `system.backups` | Show backup records |
| POST | `/system/backups` | `system.backups.create` | Create backup snapshot |
| POST | `/system/backups/cleanup` | `system.backups.cleanup` | Clean old backup snapshots |
| DELETE | `/system/backups/{backupRecord}` | `system.backups.destroy` | Delete backup snapshot |
| POST | `/logout` | `logout` | End authenticated session |

Current routing limitations:

- No user-management routes.
- No public JSON API routes.
- Home page and language switch still use route closures.

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
- Admin and Staff can ask TingHao Agent to plan restock for low-stock ingredients.
- Admin can manage restock requests.
- Admin and Staff can view expiry tracking.
- Admin can remove expired stock.
- Admin and Staff can view expiry loss recommendations.
- Admin can run expiry loss scans and review/dismiss/complete recommendations.
- Staff cannot run expiry loss scans or change recommendation status.

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
- Admin can clean up and delete backup snapshot records.

Purchase order permissions:

- Admin can view all purchase orders.
- Staff can view purchase orders they requested.
- Admin can create, edit, delete, prepare or record supplier email steps, confirm sent purchase orders, receive goods, record allocation/damage/shortage, manage supplier returns, and close real purchase orders.
- Admin can approve or reject agent-created purchase order drafts.
- Admin can generate, regenerate, approve, mark supplier email drafts sent for demo, or explicitly send approved drafts through Resend when real delivery is enabled.
- Existing supplier email drafts are reused instead of calling Qwen again.
- Staff Dashboard Autopilot Actions and Agent Audit summary cards do not link staff to other users' PO approval or supplier email draft records.
- Assigned staff can receive goods and record allocation/damage/shortage for their requested purchase orders.
- Receiving is status-gated to confirmed and partially received purchase orders for both admin and assigned staff.
- Staff cannot close real purchase orders or update supplier return statuses.
- Staff cannot generate, regenerate, approve, mark sent, or send supplier email drafts through Resend.
- Admin and Staff can view purchase order demo records.
- Admin can create, send demo email, confirm supplier, and close demo purchase orders.
- Admin and Staff can receive demo purchase order stock.

TingHao Agent permissions:

- Admin and Staff can open `/agent` as an audit/visualizer page. The old message-entry form is removed from the UI, while the backend `POST /agent/run` route remains preserved for compatibility and tests.
- Admin and Staff can trigger embedded restock planning from low-stock and inventory workflows.
- Admin can view all agent runs.
- Staff can view only their own agent runs.
- Staff can create pending-approval PO drafts through the agent.
- Admin can approve or reject those drafts.

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
- `database/migrations/2026_06_25_000006_create_purchase_orders_table.php`
- `database/migrations/2026_06_25_000007_create_purchase_order_items_table.php`
- `database/migrations/2026_06_25_000008_create_purchase_order_demos_table.php`
- `database/migrations/2026_06_25_000009_create_purchase_order_demo_items_table.php`
- `database/migrations/2026_06_25_000010_add_ingredient_id_to_purchase_order_demo_items_table.php`

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
| `purchase_orders` | Real supplier purchase order records |
| `purchase_order_items` | Real purchase order line items and received quantities |
| `purchase_order_demos` | Presentation-ready purchase order workflow records |
| `purchase_order_demo_items` | Demo purchase order line items and receiving progress |
| `agent_runs` | TingHao Agent procurement message run records |
| `agent_tool_calls` | Agent internal tool call timeline records |
| `agent_reasoning_steps` | Safe structured reasoning activity without raw chain-of-thought |
| `approval_requests` | Human review checkpoints for agent-created purchase order drafts |
| `supplier_email_drafts` | Qwen-generated supplier email drafts linked to real purchase orders |
| `expiry_loss_recommendations` | Qwen-generated expiry loss prevention recommendations with RM impact |

Current database limitations:

- No sales table.
- No roles or permissions tables.
- No full GRN table.
- Full GRN document generation is not implemented yet.
- TingHao Agent creates pending-approval PO drafts and approval-gated supplier email drafts but does not send real supplier communications from the agent loop.
- TingHao Agent expiry loss prevention scans use real inventory expiry, quantity, and cost fields.

Performance notes:

- Dashboard summary data is cached for 60 seconds.
- Low-stock, expiry, and report pages are paginated.
- Public and login pages still depend on remote imagery and should later move to local optimized assets.

### Seeder

File:

- `database/seeders/DatabaseSeeder.php`

Current functions:

- Creates or updates the admin account.
- Creates or updates the staff account.
- Creates starter categories: Flour, Sugar, Dairy, Leavening, Packaging.
- Creates demo suppliers.
- Creates demo ingredients for presentation.
- Creates demo supplier/ingredient coverage for TingHao Agent prompts, including Supplier Ali and Whole Milk Carton.
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
- Core inventory demo flow test verifies admin/staff dashboard, inventory, low stock, expiry, supplier, and report pages load and that stock-out cannot create negative stock.
- Agent audit feature test verifies mock-mode runs, persisted tool calls, own-run staff access, and admin all-run access.
- Agent audit feature test verifies PO draft creation, approval request creation, admin approve/reject behavior, supplier email draft generation/approval/mark-sent without real mail, and expiry loss scan/recommendation status behavior.

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
- `AGENTS.md`
- `docs/CHANGELOG.md`
- `docs/backend-api.md`
- `docs/database.md`
- `docs/prd.md`
- `docs/TODO.md`
- `docs/ui-guide.md`

Current documentation functions:

- `readme.md` describes the desired Ting Hao inventory management system vision.
- `docs/implementation-reference.md` records earlier implementation notes.
- `docs/LARAVEL_README.md` appears to preserve Laravel framework reference content.

Important distinction:

- The README describes planned project features.
- This inventory describes what is actually implemented now.
- `AGENTS.md` requires documentation updates before any future coding task is considered complete.

## 9. Planned But Not Yet Implemented

The following features are described or implied by the project idea, but are not built yet:

- User management for staff accounts.
- Admin Excel report upload/download.
- Sales entry.
- Real product search.
- Real map/contact integration.
- Local image asset management.
- Full GRN workflow.
- Production Stock Planner persistence and calendar history logic.
- Production TingHao Agent supplier communication delivery.
- Full recipe, POS, or demand forecasting logic for expiry recommendations.

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
# Maintenance note (2026-07-18)

The shared language selector reserves a dedicated header area on dashboard pages so it remains separate from the Admin/profile action. This is a CSS-only adjustment; language routes and role behavior are unchanged.

## Purchase Orders Index QA (2026-07-18)

- `GET /purchase-orders` presents status-specific next steps instead of a generic receiving warning.
- Confirmed and partially received POs expose `Receive Goods` and `Continue Receiving`; received, closed, rejected, and cancelled POs show non-actionable completion guidance.
- Stock Planner-created POs retain the compact `Created from Stock Prediction` source badge.
- Admin-only edit, supplier email, approval review, confirmation, and close workflow controls remain hidden from staff.
- The index table has responsive column priorities and a mobile card layout, with no horizontal table scrolling required.

## Create Purchase Order Suggestions (2026-07-18)

- `GET /purchase-orders/create` provides editable delivery, quantity, unit, and unit-price suggestions to admins.
- `GET /purchase-orders/suggestions` returns JSON for selected supplier, ingredient, and order date; it is admin-only.
- Quantity uses a positive cached Stock Planner value when available, otherwise stock-level fallback logic. The endpoint never refreshes FastAPI.
- Price preference is latest same-supplier ingredient PO, latest ingredient PO from any supplier, then ingredient cost price.
- Delivery preference is average actual lead time from received/closed supplier POs, optional supplier default attributes, then two days.
- Suggestions do not save, create, approve, email, or submit a PO; the admin must submit the existing form manually.

## Purchase Order Detail Workflow QA (2026-07-18)

- `GET /purchase-orders/{purchaseOrder}` selects one workflow timeline from PO origin and status.
- Manual timeline: Draft, Email Sent, Supplier Confirmed, Received, Closed.
- Agent/Stock Prediction timeline: PO Drafted, Admin Approved, Email Drafted, Email Approved, Marked Sent; rejection appears only on rejected POs.
- The Next step panel exposes only the valid action for the current status. Closed, rejected, and cancelled POs are guidance-only.
- Supplier confirmation is now valid only for `sent`; receiving remains valid only for `confirmed` and `partially_received`.
- Opening the detail page performs no Qwen request. Supplier email generation remains an explicit admin POST action.

## Goods Receiving Worksheet QA (2026-07-18)

- `GET /purchase-orders/{purchaseOrder}/receive` defaults a clean, fully accepted row to `Accepted / Good` instead of `Not set`.
- Editing received, accepted, damaged, returned, or shortage quantities updates the visible quality status using the controller's existing inference order.
- Quarantine / Damaged allocation is neutral when damage and return are zero, and warning-styled when either is greater than zero.
- The worksheet shows the receiving equation and explains that returns are tracked separately; top and bottom submit controls post to the same existing receive route.
- Inventory mutation remains unchanged: only accepted quantity is added to ingredient stock after Record Receiving succeeds.

## Purchase Order Detail Timeline Evidence (2026-07-18)

- `GET /purchase-orders/{purchaseOrder}` uses `sent_at` as the completion evidence for the manual Email Sent step.
- A sent `supplier_email_drafts` record without a PO `sent_at` timestamp is labelled `Marked Sent` rather than Email Sent.
- For status `received`, Draft/Supplier Confirmed/Received are completed when supported by their state, while Closed is the amber next step.
- Manual POs display Approved by as `Not applicable`; approval-based POs continue to show pending, approved, or rejected admin state.
- No PO actions, controllers, permissions, email execution, or Qwen behavior changed.

## Agent Workflow Visualizer Simplification (2026-07-18)

- Template View renders only the generic ten-node procurement architecture and does not display selected-run owner/status cards.
- Live Run View renders ten procurement nodes for stock prediction/restock/procurement runs or seven expiry-specific nodes for `expiry_loss_prevention` runs.
- Only one Selected Step Details panel is rendered; node clicks update step, status, tool label, business detail, linked record, and human checkpoint badge.
- Expiry runs read existing scan/calculation/recommendation tool calls and `expiry_loss_recommendations`; they do not show Supplier, PO, or Email nodes.
- Recent missions, proof links, expiry callout, route permissions, and audit data remain available.

## Responsive Authenticated Workflow (2026-07-18)

- Shared authenticated pages are width-constrained and prevent document-level horizontal scrolling.
- Dashboard keeps a 252px fixed sidebar on desktop/laptop and uses the existing accessible drawer at 1023px and below. Navigation scrolls vertically inside the full-height sidebar.
- Dashboard grids use six/three/two/one metric columns as space permits; autopilot and management cards use four/two/one columns; analytics and recent lists stack before content becomes cramped.
- Stock Planner uses three prediction columns on desktop, two on tablet, and one on mobile. Calendar advice stacks below the calendar at tablet width, while phones scroll the seven-day calendar inside its own focusable region.
- Purchase Order and Agent Audit tables remain in contained responsive cards. PO detail summaries, timelines, actions, receiving fields, stock allocations, and supplier draft content stack at narrow widths.
- This is presentation-only. Routes, controllers, models, Qwen/FastAPI behavior, database writes, and admin/staff permissions are unchanged.

## Phase 1 Autopilot Inventory and Procurement (2026-07-18)

- `php artisan tinghao:autopilot-scan` observes low-stock and seven-day expiry-risk ingredients, reuses per-ingredient FastAPI cache, and stores one deduplicated `autopilot_inventory_scan` AgentRun with safe tool and reasoning summaries.
- Deterministic scans never call Qwen. Only `add_stock_now` and `add_stock_soon` enter supplier comparison or optional draft planning.
- `SupplierComparisonService` ranks eligible assigned, historical, and same-category suppliers using actual price, lead-time, receiving exception, and contact evidence. It does not manufacture a numeric score; gaps are labelled `Insufficient history`.
- Restock quantity uses valid FastAPI output or `max(minimum_stock * 2 - current_quantity, minimum_stock)`, accounts for pending quantity, and remains positive before a draft can be created.
- `AUTOPILOT_PO_DRAFT_ENABLED=false` is the default. When enabled, only predictions meeting `AUTOPILOT_MINIMUM_CONFIDENCE` can create one non-duplicate `pending_approval` PO draft.
- Supplier email subject/body can be edited by admin. Editing an approved draft returns it to `draft` and requires approval again.
- `REAL_EMAIL_ENABLED=false` keeps demo-safe Mark Email as Sent. When enabled and Resend is configured, only an admin can explicitly send an approved draft linked to an approved PO.
- `RESEND_TEST_MODE=true` permits only `RESEND_TEST_RECIPIENT`, uses `onboarding@resend.dev`, labels the UI as Resend Test Mode, and stores only safe acceptance or failure metadata.
- Delivery outcome stores safe provider status and timestamps. Supplier confirmation, receiving totals including damage/return/shortage, and PO closure append tool evidence to the linked AgentRun.
- `/agent` and `/demo` show the compact real-record Phase 1 capability map. Staff `/agent` evidence is scoped to their own runs and requested POs.

## Supplier Email Draft Legacy Schema Compatibility (2026-07-19)

- Admin draft edits, demo-safe Mark Email as Sent, and explicit Resend delivery preserve their core state changes when a legacy deployment does not yet have the nullable delivery-audit columns.
- The optional `delivery_status`, `delivery_provider`, `delivery_metadata`, and `last_delivery_attempt_at` fields are written only after their migration exists. This avoids a 500 error without relaxing the admin-only workflow.
- Applying `2026_07_18_000001_add_delivery_audit_to_supplier_email_drafts.php` remains required for persisted delivery evidence.

## Bounded Qwen Restock Decision Loop (2026-07-19)

- `POST /stock-planner/ingredient/{ingredient}/plan-restock` creates a `stock_prediction_restock` AgentRun and asks Qwen for one state-valid next action per iteration, up to four iterations.
- Laravel preloads compact inventory and FastAPI prediction observations, validates Qwen actions, and executes only `get_inventory`, `read_stock_prediction`, `check_open_purchase_order`, `compare_suppliers`, `create_purchase_order_draft`, `request_human_approval`, `require_expiry_review`, or `stop`.
- Eligible items can reach one `pending_approval` PO; duplicate items stop with `duplicate_po_found`; expired items stop with `expiry_review_required`. No branch approves a PO, sends email, or mutates stock.
- `/agent/runs/{agentRun}` displays the compact observation/action/result/reason/stop audit. Raw Qwen output and chain-of-thought are not persisted.
- Staff may start the existing restock plan but cannot approve its resulting PO. Admin retains the existing approval/rejection controls.

## Agent Audit Visualizer (2026-07-19)

- `GET /agent?run={agentRun}` displays one consolidated audit view for the selected permitted run.
- Run Summary shows run ID, mission type, status, start time, owner, Qwen mode, and human approval state.
- Workflow Strip maps persisted evidence to Trigger, Observe, Predict, Decide, Approve, Act, Verify, and Audit using completed, pending, failed, or skipped states.
- Audit Timeline combines safe `agent_reasoning_steps` with non-duplicated `agent_tool_calls`; linked reasoning/tool records are shown once.
- Human Checkpoint reads PO `approval_requests` or expiry recommendation review state. Final Outcome links real PO and email records where present and marks irrelevant outcomes as not used.
- Technical Audit Details is collapsed by default and lists only tool IDs, names, and statuses; full technical evidence remains at `/agent/runs/{agentRun}`.
- Admin sees all runs. Staff selection remains scoped to runs they own.

## Agent Audit Milestone View (2026-07-19)

- The selected live mission is the default `/agent` evidence view; the static Phase 1 capability map is collapsed.
- Audit activity is grouped into at most seven milestones: Trigger Received, Request Interpreted, Inventory and Prediction Checked, Reorder and Supplier Decided, PO Draft Prepared, Human Approval, and Supplier Action and Audit Completed.
- One Selected Step Details panel shows only populated result, action, decision, confidence, approval, reason, tool, reviewer, and record fields for the clicked milestone.
- Individual tool IDs/names/statuses remain under collapsed Technical Audit Details, with the full run audit still available at `/agent/runs/{agentRun}`.
# AI Provider Architecture (2026-07-21)

- Added `StructuredDecisionProvider` as a provider-neutral structured JSON contract.
- `QwenStructuredProvider` delegates to the existing `QwenClient`.
- `OpenAIClient` supports environment-configured GPT-5.6 Responses API calls and safe mock mode.
- No existing workflow currently selects or invokes the new provider contract.
# GPT Procurement Review (2026-07-21)

- `GptProcurementReviewService` prepares structured procurement context and validates provider recommendations.
- It reuses `SupplierComparisonService` and always returns `human_approval_required=true`.
- It performs no procurement mutations or external delivery actions.
