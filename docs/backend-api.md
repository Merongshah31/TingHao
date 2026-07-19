# Ting Hao Backend API Documentation

Last reviewed: 2026-07-19

This document explains the current Laravel backend surface for Ting Hao and the recommended future JSON API structure. The current system is a Laravel Blade application, so most backend actions are web routes that return HTML pages or redirects instead of JSON.

2026-07-19 test isolation note: feature tests set `autopilot.real_email_enabled`, Resend test-mode, and fake provider credentials through `config([...])`; backend routes never read test behavior from local `.env` values during automated verification.

2026-07-19 restock decision-loop note: PredictionRestockPlanningService now exposes state-based allowed actions, rejects premature stop selections, records safe rejection metadata, and falls back to the required Laravel action.

## 1. Backend Architecture

```text
Browser
  -> Laravel web routes
  -> Controllers
  -> Eloquent models
  -> Supabase PostgreSQL

Future Smart Stock Planner integration:

Laravel
  -> prediction-service FastAPI
  -> rule-based stock action prediction
```

Current backend style:

- Authentication uses Laravel session login.
- Dashboard sidebar grouping is handled in Blade/CSS and reuses existing named routes; the 2026-07-08 sidebar hardening did not add or remove backend routes.
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
| GET | `/demo` | `demo` | Public | Judge-friendly demo guide |
| GET | `/health` | `health` | Public | Safe deployment health JSON |
| GET | `/agent/proof` | `agent.proof` | Public | Safe Alibaba Cloud/Qwen proof JSON |

Dashboard data includes:

- Ingredient count.
- Low-stock count.
- Expiring count.
- Supplier count.
- Stock movement count.
- Open restock request count.
- Open supplier return count.
- Purchase orders with shortage/damaged receiving count.
- Receiving discrepancy count.
- Inventory value.
- Stock health percentage.
- Stock in/out movement mix.
- Lowest stock list.
- Recent stock movements.
- Today's Autopilot Actions for low stock, PO approval, supplier email draft review or mark-sent, and expiry-loss risk.

Implementation notes:

- Dashboard summaries use a short-lived cache key defined in `DashboardController::CACHE_KEY`.
- Dashboard Autopilot Actions are built from live records outside the summary cache so permission-scoped PO/email links stay current.
- Approved purchase orders without an email draft expose a `Needs email draft` display status while retaining their actual approved database status.
- Cached add-stock predictions with a missing or non-positive suggested quantity use a dashboard-only fallback based on the ingredient minimum stock; non-purchase actions return business-friendly display summaries.
- Admin Dashboard Autopilot Actions can show global pending PO and supplier email draft work; staff cards for PO approval and supplier email drafts are limited to purchase orders where `purchase_orders.requested_by` is the current staff user.
- Staff pending PO Autopilot cards route to the PO review page with Review wording; admin cards keep approval wording and admin-only approval actions.
- Recent movement rows are serialized as plain arrays containing movement type, quantity, ingredient name, creator name, and formatted timestamp before reaching the Blade view.
- Paginated Blade pages continue using Laravel paginator links; CSS scopes paginator SVG sizing under `.pagination-wrap`.
- `/health` includes `mock_mode` and never exposes secret values.
- `/agent/proof` confirms server-side Qwen usage and never exposes API keys.

## 4.1 TingHao Agent Backend

Controller:

- `App\Http\Controllers\AgentController`

Services:

- `App\Services\Qwen\QwenClient`
- `App\Services\Agent\TingHaoAgentService`
- `App\Services\Agent\ProcurementMessageParserService`
- `App\Services\Agent\InventoryLookupToolService`
- `App\Services\Agent\SupplierLookupToolService`
- `App\Services\Agent\SupplierEmailDraftService`
- `App\Services\Agent\ExpiryLossPreventionService`
- `App\Services\Agent\ReasoningActivityService`
- `App\Services\Agent\HumanApprovalGuardService`

Routes:

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/agent` | `agent.index` | Admin, Staff | Show Agent Audit Console, Autopilot Workflow Visualizer, Qwen mode proof, aggregate stats, and recent runs |
| POST | `/agent/run` | `agent.run` | Admin, Staff | Validate message, run parser/lookups, store audit records |
| GET | `/agent/runs/{agentRun}` | `agent.runs.show` | Admin, Staff owner | Show parsed result, matched records, and tool timeline |
| GET | `/agent/expiry-loss` | `agent.expiry-loss` | Admin, Staff | Show expiry loss recommendations and RM impact |
| POST | `/agent/expiry-loss/scan` | `agent.expiry-loss.scan` | Admin | Run expiry loss prevention scan |

Request fields for `POST /agent/run`:

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `input_text` | string | Yes | Max 5000 characters |

Behavior:

- Admin can view all agent runs.
- Staff can view only their own runs.
- Qwen is called server-side only through `QwenClient`.
- Mock mode is used when `QWEN_MOCK_MODE=true` or no API key exists.
- `QwenClient` supports purpose-specific `max_tokens`, shared temperature, safe metadata, HTTP status, latency, and token usage fields when returned by Qwen.
- Procurement parsing caches identical normalized input text for `QWEN_CACHE_MINUTES`; approval actions, PO creation, and other database mutations are not cached.
- Expiry loss scans calculate ingredient risk in Laravel and send one compact Qwen request for all expiring ingredients in the scan.
- Tool calls are logged to `agent_tool_calls` for judge-visible activity.
- Approved agent-created purchase orders can move into the supplier email draft workflow without sending a real email.
- Agent mission detail and purchase order detail use `purchase-orders.generate-email-draft` as the canonical supplier email draft action; `purchase-orders.email-draft` remains only as a compatibility alias.
- Expiry loss scans create `expiry_loss_scan` agent runs and log scan, calculation, Qwen recommendation, and save tool calls.
- Reasoning Activity is logged to `agent_reasoning_steps` as safe summaries, evidence, confidence, risk level, related tool call, and human checkpoint metadata.
- Agent Audit remains the audit and judge view for missions, reasoning activity, tool calls, and Qwen usage proof.
- Daily workflow pages surface agent recommendations directly on Dashboard, Low Stock, Inventory detail, Purchase Orders, Supplier Email Drafts, and Expiry pages.
- Daily workflow pages show business summaries, status badges, and human approval prompts first; technical activity remains available under Advanced Details or `/agent`.
- Agent Audit also returns aggregate command-center stats for pending PO approvals, email drafts waiting approval, open expiry RM risk, and recent agent missions.
- Agent Audit aggregate stats are global for admin. Staff aggregate stats are scoped to the staff user's approval requests, supplier email drafts attached to their requested purchase orders, and their own recent agent missions.
- Agent Audit builds one Autopilot Workflow map from existing records. Template View is a static procurement architecture; Live Run View uses the latest visible AgentRun or the optional `?run={id}` query parameter and selects a procurement or expiry-loss node set from run intent/input type.
- Live Run status mapping is read-only: completed nodes come from completed tool calls or linked business records, pending nodes come from human review checkpoints, blocked/failed nodes come from rejected or skipped/failed states, and missing evidence is described as `Not recorded` in the single detail panel.
- Staff Live Run View queries remain scoped to the staff user's own AgentRun records. Admin can select any visible recent run.
- The `/agent` UI no longer exposes the old procurement message textarea or sample prompt buttons. `POST /agent/run` is intentionally preserved for existing tests, direct integrations, and historical audit workflow compatibility.

Qwen configuration:

| Env | Default | Purpose |
| --- | --- | --- |
| `QWEN_MAX_TOKENS_PARSE` | `350` | Procurement parsing response budget |
| `QWEN_MAX_TOKENS_EMAIL` | `500` | Supplier email draft response budget |
| `QWEN_MAX_TOKENS_EMAIL_DRAFT` | `500` | Supplier email draft response budget, preferred over the legacy email key |
| `QWEN_MAX_TOKENS_EXPIRY` | `350` | Expiry recommendation batch response budget |
| `QWEN_MAX_TOKENS_STOCK_REASONING` | `300` | Stock prediction explanation response budget |
| `QWEN_MAX_TOKENS_RESTOCK_DECISION` | `220` | Per-iteration bounded restock decision budget |
| `QWEN_TEMPERATURE` | `0.2` | Shared low-variance generation setting |
| `QWEN_CACHE_MINUTES` | `30` | Procurement parse cache duration |
| `QWEN_STOCK_REASONING_CACHE_MINUTES` | `30` | Stock prediction explanation cache duration |
| `QWEN_EMAIL_DRAFT_CACHE_MINUTES` | `30` | Reserved email draft cache duration; saved draft reuse is the primary duplicate-call guard |

## 4.2 Stock Prediction Service API

Location:

- `prediction-service/`

Runtime:

- Python FastAPI, intended to run locally on port `8001` during the MVP phase.

Responsibility boundary:

- Laravel remains responsible for UI, database, inventory workflow, purchase orders, approval, and audit logs.
- The FastAPI service is responsible only for deterministic stock action prediction.
- Qwen is not called by this service. A later phase may use Qwen only to explain the returned prediction in plain business language.

Routes:

| Method | URI | Access | Purpose |
| --- | --- | --- | --- |
| GET | `/health` | Local service | Return service health JSON |
| POST | `/predict-stock-action` | Local service | Return stock action recommendation JSON |

`GET /health` response:

```json
{
  "status": "ok",
  "service": "Ting Hao Stock Prediction Service"
}
```

`POST /predict-stock-action` request fields:

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `ingredient` | string | No | Ingredient name for response display |
| `current_quantity` | number/null | No | Current stock on hand |
| `unit` | string/null | No | Display unit such as kg |
| `minimum_stock` | number/null | No | Low-stock threshold |
| `stock_out_last_7_days` | number/null | No | Recent usage total |
| `stock_out_last_14_days` | number/null | No | Preferred usage total when available |
| `stock_out_last_30_days` | number/null | No | Fallback usage total |
| `expiry_days_remaining` | integer/null | No | Days before expiry |
| `pending_po_quantity` | number/null | No | Quantity already ordered but not received |
| `supplier_lead_time_days` | integer/null | No | Supplier lead time |
| `weekend_near` | boolean/null | No | Adds demand risk |
| `festival_near` | boolean/null | No | Adds demand risk |

Response behavior:

- Calculates 7-day, 14-day, and 30-day average daily usage.
- Prefers 14-day usage when available, otherwise 7-day, otherwise 30-day.
- Adjusts demand risk when weekend or festival flags are true.
- Returns `recommended_action`, `estimated_days_until_stockout`, `suggested_quantity`, `risk_level`, `confidence`, `reason_codes`, and `calculation_summary`.
- Returns `monitor` with `insufficient_usage_data` when usage totals are missing or zero, unless a real minimum-stock threshold is breached.
- Does not create purchase orders or mutate Laravel data.

### Laravel Stock Planner Integration

Configuration:

| Env | Default | Purpose |
| --- | --- | --- |
| `STOCK_PREDICTION_API_URL` | `http://127.0.0.1:8001` | FastAPI service base URL |
| `STOCK_PREDICTION_TIMEOUT` | `8` | Laravel HTTP timeout in seconds |
| `STOCK_PREDICTION_CACHE_MINUTES` | `30` | Laravel cache duration for per-ingredient prediction results |

Laravel services:

- `App\Services\Stock\StockPredictionInputBuilder`
- `App\Services\Stock\StockPredictionApiClient`
- `App\Services\Agent\PredictionRestockPlanningService`

Laravel routes:

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/stock-planner?view=cards` | `stock-planner.index` | Admin, Staff | List ingredients with cached or newly generated prediction cards |
| GET | `/stock-planner?view=calendar` | `stock-planner.index` | Admin, Staff | Show date-based stock planning signals from the same prediction results |
| GET | `/stock-planner/ingredient/{ingredient}/prediction` | `stock-planner.prediction` | Admin, Staff | Show one ingredient prediction detail and compact input summary |
| POST | `/stock-planner/ingredient/{ingredient}/refresh-prediction` | `stock-planner.refresh-prediction` | Admin, Staff | Force a new FastAPI prediction call for one ingredient |
| POST | `/stock-planner/ingredient/{ingredient}/explain` | `stock-planner.explain` | Admin, Staff | Generate or regenerate an English-only Qwen explanation for the latest prediction snapshot |
| POST | `/stock-planner/ingredient/{ingredient}/plan-restock` | `stock-planner.plan-restock` | Admin, Staff | Create a restock autopilot task and pending-approval PO draft for eligible add-stock predictions |
| GET | `/stock-memory-demo` | `stock-memory.demo` | Admin, Staff | Redirect to `/stock-planner?view=calendar` |
| GET | `/calendar-demo` | `stock-planner.calendar-redirect` | Admin, Staff | Redirect to `/stock-planner?view=calendar` |

Compact payload behavior:

- Laravel sends one ingredient at a time.
- Laravel sends summarized stock-out totals for the last 7, 14, and 30 days.
- Laravel sends pending PO quantity as a summarized open quantity, not full PO rows.
- Laravel sends expiry days remaining, weekend risk, and festival risk flags.
- Laravel does not send full stock movement history, full purchase order records, full supplier records, API keys, Qwen prompts, or raw database tables.
- Prediction results are cached using `STOCK_PREDICTION_CACHE_MINUTES`.
- Normal Stock Planner page refreshes reuse cached per-ingredient prediction arrays while the cache is valid.
- Prediction View loads predictions only for the visible card page.
- Calendar View uses the same prediction cache/result shape as Prediction View, does not preload Prediction View card predictions before building calendar signals, and does not use hardcoded static calendar advice.
- Force refresh bypasses the cache and calls FastAPI again for one selected ingredient only.
- Local environment logging records stock prediction cache `hit`, `miss`, and `refresh` events with ingredient ID/name and cache key.

Restock planning behavior:

- Only FastAPI actions `add_stock_now` and `add_stock_soon` may create a restock workflow.
- `do_not_buy`, `buy_less`, `monitor`, and `use_before_expiry` return friendly advice and do not create purchase orders.
- Laravel checks for an existing open PO for the same ingredient before creating a new draft.
- Supplier selection uses the ingredient-linked supplier first; if missing, Laravel infers a supplier from other ingredients in the same category when possible.
- Suggested quantity comes from FastAPI when positive, otherwise Laravel falls back to `max(minimum_stock * 2 - current_quantity, minimum_stock)`.
- Successful planning writes existing `agent_runs`, `agent_tool_calls`, `agent_reasoning_steps`, `purchase_orders`, `purchase_order_items`, and `approval_requests` records.
- New POs start as `pending_approval`; admin approval is still required before supplier email drafting or later PO steps.
- Qwen is not called during PO creation. The workflow only attaches a cached Qwen explanation when one already exists.

Failure behavior:

- `StockPredictionApiClient` catches timeouts, connection failures, invalid JSON, and non-success HTTP responses.
- Laravel pages receive `available: false` and `message: Prediction service unavailable`.
- Dashboard reads cached prediction signals only and does not call FastAPI directly.
- Fresh and cached Stock Planner predictions pass through the same Laravel business-rule reconciliation using current quantity, minimum stock, and expiry facts.
- Add-stock responses with null, invalid, or non-positive quantities use `max(minimum_stock * 2 - current_quantity, minimum_stock)` with a final positive safety floor.
- Non-purchase actions normalize `suggested_quantity` to `null` for Blade display and include `purchase_guidance`; the original FastAPI response remains available only in collapsed Advanced Details.
- Expired stock keeps a non-buy backend action but uses the display action `review_expired_stock`, preventing PO creation while providing a clear operator label.
- `PredictionRestockPlanningService::availability()` exposes UI-safe restock eligibility and the service still validates action, supplier, and positive quantity server-side before creating a PO draft.
- Stock Planner detail applies business rules again with the full compact input, including `expiry_days_remaining` and `pending_po_quantity`, after reading cached prediction data.
- Restock availability returns the latest matching active purchase order when pending quantity is positive so Blade can link to it.
- `PredictionRestockPlanningService::plan()` blocks expired stock and pending-PO input independently of button visibility, preserving direct-URL safety.

### Qwen Stock Prediction Explanation

Service:

- `App\Services\Agent\StockPredictionReasoningService`

Responsibility boundary:

- FastAPI calculates the prediction.
- Laravel manages routes, cache, UI, restock buttons, and workflow safety.
- Qwen explains the prediction result in simple professional English only.

Qwen payload:

- Ingredient name.
- Current quantity, unit, and minimum stock.
- FastAPI `recommended_action`, `estimated_days_until_stockout`, `suggested_quantity`, `risk_level`, `confidence`, and `reason_codes`.
- Compact calculation summary containing average daily usage and pending PO quantity.

Qwen does not receive:

- Full stock movement history.
- Full purchase order records.
- Full supplier records.
- API keys.
- Raw chain-of-thought.
- Permission or approval authority.
- Malay/mixed-language wording.
- Customer behavior, competitors, sales, demand, or market activity unless explicitly provided in the prediction facts.

Failure and cache behavior:

- Explanations are cached by ingredient ID plus prediction snapshot hash.
- Reopening the same prediction detail reuses the cached explanation.
- Posting to `stock-planner.explain` regenerates and replaces the cached English explanation for the current prediction snapshot.
- The service rejects common Malay/mixed-language output from Qwen and falls back to deterministic English explanation text.
- Stock Planner Prediction View cards, Calendar View, and Dashboard do not call Qwen.
- If Qwen is unavailable, the prediction remains visible and the UI shows: `Prediction is available, but AI explanation is temporarily unavailable.`
- Advanced Details show Qwen model, mock mode, response latency, token usage, max token budget, and cache hit/miss only.

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
| POST | `/alerts/low-stock/{ingredient}/restock` | `alerts.restock.request` | Admin, Staff | Create restock request |
| POST | `/alerts/low-stock/{ingredient}/agent-plan` | `alerts.restock.agent-plan` | Admin, Staff | Ask TingHao Agent to create a pending-approval PO draft from a low-stock ingredient |
| PATCH | `/alerts/restock/{restockRequest}` | `alerts.restock.update` | Admin | Update restock status |

Low-stock rule:

```text
ingredient.quantity <= ingredient.minimum_stock
```

Embedded agent behavior:

- `LowStockController@planRestockWithAgent` builds a procurement prompt from the selected ingredient's current quantity, minimum stock, unit, shortage, and linked supplier when available.
- `TingHaoAgentService` reuses the existing parser, inventory lookup, supplier ranking, restock planning, PO draft, approval request, tool-call, and reasoning activity services.
- The user is redirected to the generated PO detail when a pending-approval draft is created, or to the agent run audit page when no actionable PO can be created.

## 8. Purchase Order Backend

Models:

- `App\Models\PurchaseOrder`
- `App\Models\PurchaseOrderItem`
- `App\Models\StockLocation`
- `App\Models\StockAllocation`
- `App\Models\SupplierReturn`
- `App\Models\PurchaseOrderDemo`
- `App\Models\PurchaseOrderDemoItem`

### Real Purchase Order Routes

| Method | URI | Route Name | Controller Method | Access | Purpose |
| --- | --- | --- | --- | --- | --- |
| GET | `/purchase-orders` | `purchase-orders.index` | `PurchaseOrderController@index` | Admin, Staff | List purchase orders |
| GET | `/purchase-orders/create/from-low-stock` | `purchase-orders.create-from-low-stock` | `PurchaseOrderController@createFromLowStock` | Admin | Create PO from low-stock items |
| GET | `/purchase-orders/create` | `purchase-orders.create` | `PurchaseOrderController@create` | Admin | Show PO form |
| POST | `/purchase-orders` | `purchase-orders.store` | `PurchaseOrderController@store` | Admin | Store PO |
| GET | `/purchase-orders/{purchaseOrder}` | `purchase-orders.show` | `PurchaseOrderController@show` | Admin, Staff | View PO detail |
| GET | `/purchase-orders/{purchaseOrder}/edit` | `purchase-orders.edit` | `PurchaseOrderController@edit` | Admin | Show PO edit form |
| PUT | `/purchase-orders/{purchaseOrder}` | `purchase-orders.update` | `PurchaseOrderController@update` | Admin | Update PO |
| DELETE | `/purchase-orders/{purchaseOrder}` | `purchase-orders.destroy` | `PurchaseOrderController@destroy` | Admin | Delete PO |
| POST | `/purchase-orders/{purchaseOrder}/send-email` | `purchase-orders.send-email` | `PurchaseOrderController@sendEmail` | Admin | Send supplier email and mark PO sent |
| POST | `/purchase-orders/{purchaseOrder}/generate-email-draft` | `purchase-orders.generate-email-draft` | `SupplierEmailDraftController@generate` | Admin | Generate supplier email draft for an approved PO |
| POST | `/purchase-orders/{purchaseOrder}/email-draft` | `purchase-orders.email-draft` | `SupplierEmailDraftController@generate` | Admin | Compatibility alias for supplier email draft generation |
| POST | `/purchase-orders/{purchaseOrder}/approve` | `purchase-orders.approve` | `PurchaseOrderController@approve` | Admin | Approve pending agent PO draft |
| POST | `/purchase-orders/{purchaseOrder}/reject` | `purchase-orders.reject` | `PurchaseOrderController@reject` | Admin | Reject pending agent PO draft |
| POST | `/purchase-orders/{purchaseOrder}/confirm` | `purchase-orders.confirm` | `PurchaseOrderController@confirm` | Admin | Confirm a sent PO before receiving |
| GET | `/purchase-orders/{purchaseOrder}/receive` | `purchase-orders.receive-form` | `PurchaseOrderController@receiveForm` | Admin, assigned Staff | Show goods receiving and allocation form for confirmed or partially received POs |
| POST | `/purchase-orders/{purchaseOrder}/receive` | `purchase-orders.receive` | `PurchaseOrderController@receive` | Admin, assigned Staff | Record PO receiving breakdown, stock allocations, stock-in movements, and supplier returns |
| POST | `/purchase-orders/{purchaseOrder}/close` | `purchase-orders.close` | `PurchaseOrderController@close` | Admin | Close fully received PO |
| GET | `/supplier-returns` | `supplier-returns.index` | `SupplierReturnController@index` | Admin, Staff | List supplier returns |
| GET | `/supplier-returns/{supplierReturn}` | `supplier-returns.show` | `SupplierReturnController@show` | Admin, Staff | Show supplier return detail |
| PATCH | `/supplier-returns/{supplierReturn}` | `supplier-returns.update` | `SupplierReturnController@update` | Admin | Update supplier return status/reason |

Real PO status flow:

```text
draft -> confirmed -> partially_received -> received -> closed
draft -> sent -> confirmed -> partially_received -> received -> closed
approved -> confirmed -> partially_received -> received -> closed
```

Agent PO approval flow:

```text
pending_approval -> approved | rejected
```

Supplier email draft flow:

```text
approved PO -> draft -> approved draft -> marked sent -> PO sent
```

Receiving behavior:

- Creating, emailing, and confirming a PO do not change inventory quantity.
- `POST /purchase-orders/{purchaseOrder}/confirm` can move `draft`, `approved`, or `sent` purchase orders to `confirmed`. `pending_approval` must still use the approval route first.
- The receiving form is a Blade worksheet UI; backend validation and inventory behavior remain in `PurchaseOrderController@receive`.
- `PurchaseOrderController@receiveForm` and `PurchaseOrderController@receive` create/reactivate the standard active stock locations if they are missing: Store Room, Production Area, Front Counter, and Quarantine / Damaged.
- Agent-created purchase order drafts do not send supplier emails directly.
- Phase 3 supplier email drafts are saved for review and mark-sent is demo-safe with no SMTP/Gmail/WhatsApp call.
- If a draft already exists for a PO, generation redirects to that draft and does not call Qwen again.
- Qwen receives only compact PO, supplier, item, and business-context facts; full stock movement, agent log, and database tables are not sent.
- If Qwen is unavailable outside explicit mock mode, no fake draft is saved and the PO remains `approved`.
- Supplier missing, supplier contact missing, or no-item POs return friendly validation errors instead of saving drafts.
- `approved_at`, `qwen_model`, and `qwen_metadata` are written only when those migration columns exist, so older databases keep working until migration is run.
- Admin approval/rejection updates both `purchase_orders.status` and `approval_requests.status`.
- Receiving a PO updates `purchase_order_items.received_quantity`, `accepted_quantity`, `damaged_quantity`, `returned_quantity`, `shortage_quantity`, `quality_status`, and `receiving_notes`.
- `GET /purchase-orders/{purchaseOrder}/receive` is only available for `confirmed` and `partially_received` purchase orders; invalid statuses redirect to the PO detail with a validation error.
- `received_quantity` input must equal `accepted_quantity + damaged_quantity + shortage_quantity`.
- Accepted quantity must equal usable allocations to Store Room, Production Area, and Front Counter.
- Receiving business-rule mismatches redirect back to the worksheet with validation errors and old input instead of rendering a raw 422 exception page.
- Returned or quarantined damaged quantity cannot exceed damaged quantity.
- Only accepted quantity increases `ingredients.quantity`.
- Only accepted quantity creates `stock_movements` records with type `in` and reason `PO Received Accepted Stock: {PO Number}`.
- Damaged/returned receiving creates `supplier_returns` records.
- Stock allocation rows record where accepted or quarantined stock was placed.
- PO status becomes `partially_received` until all ordered quantity is received.
- PO status becomes `received` when all item quantities are received.
- Admin can close a fully received PO.

Request fields for `POST /purchase-orders/{purchaseOrder}/receive`:

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `items[item_id][received_quantity]` | decimal | Yes for processed item | New delivery quantity being accounted for |
| `items[item_id][accepted_quantity]` | decimal | Yes for processed item | Quantity accepted into usable stock |
| `items[item_id][damaged_quantity]` | decimal | No | Quantity damaged/rejected from delivery |
| `items[item_id][returned_quantity]` | decimal | No | Quantity immediately returned to supplier |
| `items[item_id][shortage_quantity]` | decimal | No | Quantity missing/short from delivery |
| `items[item_id][quality_status]` | string | No | `accepted`, `partially_accepted`, `damaged`, `rejected`, `shortage`, or `returned` |
| `items[item_id][receiving_notes]` | string | No | Receiving note/reason |
| `items[item_id][allocations][stock_location_id]` | decimal | No | Quantity allocated to a location |

Supplier return statuses:

| Status | Meaning |
| --- | --- |
| `pending` | Return record created and waiting action |
| `sent_to_supplier` | Supplier has been notified outside the system |
| `resolved` | Return has been resolved by admin |
| `rejected_by_supplier` | Supplier rejected the return claim |

### Supplier Email Draft Routes

Controller:

- `App\Http\Controllers\SupplierEmailDraftController`

| Method | URI | Route Name | Controller Method | Access | Purpose |
| --- | --- | --- | --- | --- | --- |
| GET | `/supplier-email-drafts/{supplierEmailDraft}` | `supplier-email-drafts.show` | `SupplierEmailDraftController@show` | Admin, Staff owner | View generated supplier email draft |
| POST | `/supplier-email-drafts/{supplierEmailDraft}/approve` | `supplier-email-drafts.approve` | `SupplierEmailDraftController@approve` | Admin | Approve generated draft |
| POST | `/supplier-email-drafts/{supplierEmailDraft}/mark-sent` | `supplier-email-drafts.mark-sent` | `SupplierEmailDraftController@markSent` | Admin | Mark sent for demo and set PO status to sent |
| POST | `/supplier-email-drafts/{supplierEmailDraft}/send-resend` | `supplier-email-drafts.send-resend` | `SupplierEmailDraftController@sendResend` | Admin | Send approved draft through Resend after explicit admin action |
| POST | `/supplier-email-drafts/{supplierEmailDraft}/regenerate` | `supplier-email-drafts.regenerate` | `SupplierEmailDraftController@regenerate` | Admin | Call Qwen again and replace the existing draft while it is still draft status |

Behavior:

- Draft generation requires an approved purchase order.
- Staff may view only drafts attached to their own requested purchase orders.
- Admin-only actions update draft status and log agent tool calls when the draft is linked to an agent run.
- Mark sent does not send any real email and is hidden when real delivery is enabled.
- Resend sending requires `REAL_EMAIL_ENABLED=true`, configured `RESEND_API_KEY`, approved PO, approved draft, valid linked supplier email, unsent draft state, and admin role.
- In `RESEND_TEST_MODE=true`, the linked supplier email must match `RESEND_TEST_RECIPIENT`; the service uses `onboarding@resend.dev` and rejects any other recipient.
- Resend success stores `provider=resend`, `provider_message_id`, `delivery_status=accepted`, `sent_at`, `sent_by`, safe `delivery_metadata`, updates the PO sent timestamp, and records `send_supplier_email_resend`.
- Resend failure leaves the draft approved and retryable, records `delivery_status=failed` plus a safe error category, and never returns API keys, authorization headers, raw provider traces, or stack traces.

### Purchase Order Demo Routes

| Method | URI | Route Name | Controller Method | Access | Purpose |
| --- | --- | --- | --- | --- | --- |
| GET | `/purchase-order-demo` | `po-demo.index` | `PurchaseOrderDemoController@index` | Admin, Staff | List demo POs |
| GET | `/purchase-order-demo/create` | `po-demo.create` | `PurchaseOrderDemoController@create` | Admin | Show demo PO form |
| POST | `/purchase-order-demo` | `po-demo.store` | `PurchaseOrderDemoController@store` | Admin | Store demo PO |
| GET | `/purchase-order-demo/{po}` | `po-demo.show` | `PurchaseOrderDemoController@show` | Admin, Staff | View demo PO detail |
| POST | `/purchase-order-demo/{po}/send-email-demo` | `po-demo.send-email` | `PurchaseOrderDemoController@sendEmailDemo` | Admin | Mark demo supplier email sent |
| POST | `/purchase-order-demo/{po}/confirm-demo` | `po-demo.confirm` | `PurchaseOrderDemoController@confirmDemo` | Admin | Mark supplier confirmed |
| POST | `/purchase-order-demo/{po}/receive-demo` | `po-demo.receive` | `PurchaseOrderDemoController@receiveDemo` | Admin, Staff | Receive demo stock |
| POST | `/purchase-order-demo/{po}/close-demo` | `po-demo.close` | `PurchaseOrderDemoController@closeDemo` | Admin | Close demo PO |

Current limitations:

- Full GRN document generation is not implemented yet.
- Demo PO routes still exist for older presentation workflows, but visible navigation now points to the real purchase order module.
- Production supplier SMTP depends on environment mail configuration.

## 9. Expiry Backend

Model:

- `Ingredient`

### Expiry Routes

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| GET | `/expiry` | `expiry.index` | Admin, Staff | View expiring and expired ingredients |
| POST | `/expiry/{ingredient}/remove` | `expiry.remove` | Admin | Remove expired stock |
| GET | `/expiry-loss-recommendations/{expiryLossRecommendation}` | `expiry-loss-recommendations.show` | Admin, Staff | View expiry loss recommendation detail |
| POST | `/expiry-loss-recommendations/{expiryLossRecommendation}/review` | `expiry-loss-recommendations.review` | Admin | Mark recommendation reviewed |
| POST | `/expiry-loss-recommendations/{expiryLossRecommendation}/dismiss` | `expiry-loss-recommendations.dismiss` | Admin | Dismiss recommendation |
| POST | `/expiry-loss-recommendations/{expiryLossRecommendation}/complete` | `expiry-loss-recommendations.complete` | Admin | Mark recommendation completed |

Expiry rules:

| Scope | Rule |
| --- | --- |
| Expiring soon | `expiry_date` is within the next 30 days |
| Expired | `expiry_date` is before today |

Expired stock removal:

- Sets stock quantity down through stock-out behavior.
- Records the removal in stock movement history.

Expiry loss prevention:

- Scans only non-expired ingredients expiring within 7 days.
- Excludes zero or negative stock.
- Calculates `potential_loss = quantity * cost_price` when cost price exists.
- Saves recommendations in `expiry_loss_recommendations`.
- Does not build recipes, POS forecasting, or Excel import/export.

## 10. Supplier Backend

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

## 11. Reports Backend

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
| GET | `/reports/generated-summary/pdf` | `reports.generated-summary.pdf` | Admin | Download generated summary PDF |

Confirmed future enhancement:

- Admin can upload and download Excel reports.
- Staff can view reports but should not upload/download Excel unless requirements change.

## 12. System Backend

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
| POST | `/system/backups/cleanup` | `system.backups.cleanup` | Admin | Clean old backup snapshots |
| DELETE | `/system/backups/{backupRecord}` | `system.backups.destroy` | Admin | Delete backup snapshot |

## 13. Utility And Demo Routes

| Method | URI | Route Name | Controller Method | Access | Purpose |
| --- | --- | --- | --- | --- | --- |
| GET | `/health` | `health` | Route closure | Public | Safe JSON health response with Qwen/database status |
| GET | `/language/{locale}` | `language.switch` | Route closure | Public | Switch `en` or `zh_CN` locale |
| GET | `/stock-memory-demo` | `stock-memory.demo` | `StockMemoryDemoController@index` | Admin, Staff | Redirect to Stock Planner Calendar View |
| GET | `/help-center` | `help-center.index` | `HelpCenterController@index` | Admin, Staff | Show help center guidance |

Performance behavior:

- Dashboard summary values are cached for 60 seconds.
- Local development requests log route name, query count, and response time through `LogLocalPerformance`.
- Report endpoints return paginated HTML tables for large datasets.
- `/health` returns `status`, `service`, `architecture`, `track`, `qwen_configured`, and `database` without exposing API keys.

## 14. Documentation Workflow

Every backend change must update the relevant documentation before the task is complete:

- Route/controller changes: update this file and `docs/current-function-inventory.md`.
- Database/migration changes: update `docs/database.md`.
- Feature scope or permission changes: update `docs/prd.md`.
- All system changes: add a `docs/CHANGELOG.md` entry and update `docs/TODO.md` when future work remains.

## 15. Recommended Future JSON API

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

## 16. Backend Files Reference

Controllers:

- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/AgentController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/IngredientController.php`
- `app/Http/Controllers/StockMovementController.php`
- `app/Http/Controllers/LowStockController.php`
- `app/Http/Controllers/ExpiryController.php`
- `app/Http/Controllers/ExpiryLossRecommendationController.php`
- `app/Http/Controllers/SupplierController.php`
- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Http/Controllers/SupplierEmailDraftController.php`
- `app/Http/Controllers/PurchaseOrderDemoController.php`
- `app/Http/Controllers/StockMemoryDemoController.php`
- `app/Http/Controllers/HelpCenterController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/SystemController.php`

Models:

- `app/Models/User.php`
- `app/Models/AgentRun.php`
- `app/Models/AgentReasoningStep.php`
- `app/Models/AgentToolCall.php`
- `app/Models/Category.php`
- `app/Models/Ingredient.php`
- `app/Models/ExpiryLossRecommendation.php`
- `app/Models/StockMovement.php`
- `app/Models/RestockRequest.php`
- `app/Models/Supplier.php`
- `app/Models/PurchaseOrder.php`
- `app/Models/PurchaseOrderItem.php`
- `app/Models/SupplierEmailDraft.php`
- `app/Models/PurchaseOrderDemo.php`
- `app/Models/PurchaseOrderDemoItem.php`
- `app/Models/SystemSetting.php`
- `app/Models/BackupRecord.php`

Route files:

- `routes/web.php`
- `routes/api.php` can be added later for JSON APIs.

## 17. Current Backend Limitations

- No JSON API is implemented yet.
- No API token authentication is implemented yet.
- No POS sale table exists yet.
- No product recipe mapping exists yet.
- Excel upload/download is confirmed but not implemented yet.
- Current backend actions mainly return Blade views and redirects.
- TingHao Agent can create pending-approval PO drafts, supplier email drafts, and expiry loss recommendations. Real supplier email delivery is available only through the explicit admin Resend action after PO and draft approval.
# Maintenance note (2026-07-18)

No backend route or language-switch behavior changed. The dashboard language selector spacing was adjusted in CSS only to prevent overlap with the header Admin/profile action.

## Purchase Orders Index Display Contract (2026-07-18)

- `GET /purchase-orders` continues to use `PurchaseOrderController@index` and returns paginated Blade HTML.
- Admins receive all POs; staff records remain filtered by `requested_by` in the existing controller query.
- The view maps PO status to the next business step: pending approval, confirm, receive, continue receiving, ready to close, completed, or no further action.
- `GET /purchase-orders/{purchaseOrder}/receive` is linked only for `confirmed` and `partially_received`; direct access remains protected by controller validation.
- No request, response, route, middleware, or API behavior changed in this QA pass.

## Create Purchase Order Suggestion Endpoint (2026-07-18)

- Route: `GET /purchase-orders/suggestions` (`purchase-orders.suggestions`).
- Controller: `PurchaseOrderController@suggestions`.
- Middleware: `web`, `auth`, `role:admin`.
- Optional query fields: `supplier_id`, `ingredient_id`, `order_date` (`Y-m-d`). IDs are validated against existing records.
- JSON fields: `suggested_quantity`, `unit`, `suggested_unit_price`, `expected_delivery_date`, `lead_time_days`, and `source.quantity|price|delivery`.
- Quantity reads only the existing `stock_prediction.ingredient.{id}.v1` Laravel cache key and applies current inventory safety rules; a cache miss uses `max(minimum_stock * 2 - current_quantity, minimum_stock, 1)`.
- The endpoint performs database/cache reads only. It does not call FastAPI, Qwen, email services, or PO creation/approval actions.

## Purchase Order Detail State Contract (2026-07-18)

- `GET /purchase-orders/{purchaseOrder}` remains an admin/staff Blade route and does not call Qwen.
- Detail actions are status-gated: draft manual PO → explicit supplier email action; pending approval → admin approve/reject; approved → admin generate/review draft; sent → admin confirm supplier; confirmed/partially received → receive; received → admin close; closed/rejected/cancelled → no mutation action.
- `POST /purchase-orders/{purchaseOrder}/confirm` uses `PurchaseOrder::canBeConfirmed()` and now returns HTTP 422 unless status is `sent`.
- Direct receive access remains guarded by `PurchaseOrder::canReceiveStock()` for `confirmed` and `partially_received` only.
- Supplier email draft generation is still the only action here that can call Qwen, and only after an explicit admin POST.

## Goods Receiving Display Contract (2026-07-18)

- `GET /purchase-orders/{purchaseOrder}/receive` remains available only to admin or the assigned staff member when the PO status is `confirmed` or `partially_received`.
- The Blade worksheet preselects the same quality status that `PurchaseOrderController@receive` infers when no explicit status is submitted: returned, damaged, shortage, partially accepted, then accepted.
- `POST /purchase-orders/{purchaseOrder}/receive` is unchanged: received must equal accepted + damaged + shortage; returned and quarantined quantities cannot exceed damaged quantity.
- Only accepted quantity updates inventory and creates usable stock-in movement/allocation records. No new endpoint, request field, or external API call was added.

## Purchase Order Detail Timeline Response Note (2026-07-18)

- `GET /purchase-orders/{purchaseOrder}` remains a Blade response with no Qwen or email call on page load.
- Manual timeline email completion requires `purchase_orders.sent_at` or an equivalent `supplier_email_drafts.status = sent` record; the latter is labelled `Marked Sent`.
- Status `received` renders Received as completed and Closed as current, matching the existing admin close action.
- This is display-only logic. Controller transitions, POST endpoints, authorization, and response behavior are unchanged.

## Agent Visualizer Read Model (2026-07-18)

- `GET /agent?run={id}` eager-loads the selected run's tools, PO/approval/email records, and expiry-loss recommendations; it performs no Qwen call and no mutation.
- Procurement live nodes use inventory/prediction/supplier/PO/approval/email audit evidence.
- Expiry live nodes use `scan_expiring_ingredients`, `calculate_expiry_loss`, `generate_expiry_recommendation`, `save_expiry_recommendation`, and linked recommendation review status.
- The Blade response contains one Selected Step Details region. Lightweight client-side behavior swaps safe text and existing internal record links without exposing raw payloads.

## Responsive UI Route Contract Note (2026-07-18)

- No backend endpoint, method, request payload, response payload, redirect, middleware, or route-model-binding behavior changed.
- Existing authenticated GET routes for Dashboard, Stock Planner cards/calendar/detail, Purchase Orders index/detail/receive, Supplier Email Draft, and Agent Audit now render through responsive CSS at the documented breakpoints.
- The Stock Planner calendar adds only a semantic, keyboard-focusable scroll-region wrapper; prediction caching and FastAPI/Qwen call behavior are unchanged.
- Existing POST actions for prediction refresh/restock, PO approval/rejection/receiving, and supplier email draft approval/mark-sent remain unchanged and were covered by the focused feature regression suite.

## Phase 1 Autopilot Backend Contract (2026-07-18)

### Scheduled command

- Command: `php artisan tinghao:autopilot-scan`.
- Schedule: hourly with `withoutOverlapping()` in `routes/console.php`.
- Reads low-stock and expiry-risk ingredients, cache key `stock_prediction.ingredient.{id}.v1`, then calls FastAPI only for cache misses.
- A recent `autopilot_inventory_scan` within `AUTOPILOT_SCAN_DEDUPE_MINUTES` is returned instead of creating a duplicate run.
- `monitor`, `buy_less`, `do_not_buy`, and `use_before_expiry` never create a PO draft.
- Optional drafts require `AUTOPILOT_PO_DRAFT_ENABLED=true`, confidence at or above `AUTOPILOT_MINIMUM_CONFIDENCE`, a positive quantity, an eligible supplier, and no open PO/pending quantity.

### Supplier email draft routes

- `PUT /supplier-email-drafts/{supplierEmailDraft}` (`supplier-email-drafts.update`): admin only; validates subject/body, stores edits, and resets approval if required.
- `POST /supplier-email-drafts/{supplierEmailDraft}/send-resend` (`supplier-email-drafts.send-resend`): admin only; requires approved PO, approved draft, `REAL_EMAIL_ENABLED=true`, configured Resend API key, valid server-side sender, valid linked supplier email, and unsent draft state.
- Existing `POST .../mark-sent` is available only when real email is disabled. It records a demo state and never invokes mail transport.
- Resend success sets draft/PO sent state and safe acceptance metadata. Failure leaves the draft approved, records a failed delivery attempt, and returns a user-friendly validation message.

### Audit response behavior

- PO approve/reject, supplier confirmation, goods receiving, Resend success/failure, and PO closure append `AgentToolCall` plus safe `AgentReasoningStep` summaries when the PO has an `agent_run_id`.
- Receiving audit output contains only business quantities/status/timestamps; no secrets or raw chain-of-thought are stored.
- `GET /agent` scopes capability evidence to the authenticated staff member unless the viewer is admin. Public `/demo` shows operational capability status but no credentials.

## Supplier Email Draft Legacy Schema Compatibility (2026-07-19)

- The existing supplier-email draft update, demo mark-sent, and real send routes dynamically omit only missing optional delivery-audit columns on deployments that have not run the delivery-audit migration.
- Core status, approval, sent timestamp, PO transition, authorization, validation, redirects, and audit logging remain unchanged.
- Deployments must still apply `2026_07_18_000001_add_delivery_audit_to_supplier_email_drafts.php` before relying on persisted delivery provider/status/metadata evidence.

## Bounded Restock Decision Contract (2026-07-19)

- Endpoint: existing authenticated `POST /stock-planner/ingredient/{ingredient}/plan-restock`.
- Inputs to each Qwen decision are compact mission type, ingredient facts, existing FastAPI prediction summary, expiry status, pending-PO summary, supplier summary, previous result, and current allowed actions.
- Expected JSON keys are `next_action`, `reason_summary`, `required_inputs`, `confidence`, and `stop_reason`. Responses are JSON-only and raw response text is not stored.
- Laravel intersects `next_action` with the fixed allowlist and current state. Invalid, malformed, unavailable, or timed-out output falls back to the next deterministic safe action within the same iteration.
- The loop stops on `human_approval_required`, `duplicate_po_found`, `expiry_review_required`, `completed`, `blocked`, or `max_iterations_reached`; maximum decision count is four.
- Draft creation rechecks expiry, action type, pending quantity/open POs, supplier, and positive quantity inside Laravel. It creates only `pending_approval` plus an existing pending `ApprovalRequest` and then stops.
- `GET /agent/runs/{agentRun}` reads `parsed_intent.decision_loop.iterations` for the business-safe audit table. No new endpoint, public API, or response schema was introduced.
# Agent Audit Visualizer (2026-07-19)

- `GET /agent` accepts optional authenticated query `run={agentRunId}`.
- `AgentController::workflowRunFor()` applies the existing admin/staff ownership scope and eager-loads reasoning steps, linked tool calls, approval reviewers, POs, supplier email drafts, and expiry recommendation reviewers.
- `AgentController::agentAuditVisualizer()` creates a presentation-only read model. It does not call Qwen, execute tools, mutate workflow status, or expose raw request/response payloads.
- The read model contains `summary`, `workflow`, `timeline`, `checkpoint`, and `outcomes`. Missing historical evidence is represented as skipped or not recorded.
- No response schema or behavior changed for `/agent/proof` or `/agent/runs/{agentRun}`.

## Agent Audit Milestone Read Model (2026-07-19)

- `AgentController::agentAuditVisualizer()` now exposes `milestones` and `selected_milestone` instead of rendering every reasoning/tool event as a visible timeline row.
- Milestone grouping is read-only and capped at seven entries. Tool calls are classified by recorded tool name and linked reasoning type.
- `summary.agent_status` is independent from `summary.procurement_status`, which is derived from real PO, approval, expiry review, or decision-loop stop evidence.
- Optional milestone details are filtered server-side, so empty decision, confidence, approval, reviewer, or tool fields are not rendered.
- No Qwen or FastAPI request is made by `GET /agent`; the page reads persisted records only.
