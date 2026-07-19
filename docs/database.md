# Ting Hao Database Documentation

Last updated: 2026-07-19

This document records the current database tables, important fields, relationships, and notes for the Ting Hao Inventory Management System.

2026-07-08 sidebar navigation hardening note: no database tables, fields, relationships, status values, or migrations changed.

2026-07-08 Agent Workflow Visualizer note: no database tables, fields, relationships, status values, or migrations changed. The `/agent` visualizer reads existing `agent_runs`, `agent_tool_calls`, `purchase_orders`, `approval_requests`, and `supplier_email_drafts` records.

2026-07-08 Agent Audit UI simplification note: no database tables, fields, relationships, status values, or migrations changed. The old `/agent` procurement message-entry UI was removed, but existing `agent_runs` and `agent_tool_calls` records and backend routes remain unchanged.

2026-07-18 Dashboard visual/data QA note: no database tables, fields, relationships, status values, or migrations changed. `Needs email draft` and prediction quantity fallback are display-only values; purchase order and prediction records are not mutated.

2026-07-18 Stock Planner visual/data QA note: no database schema or stored records changed. `botol -> bottle`, `cadburry choc -> Cadbury Choc`, `Cookies chocholate -> Cookies Chocolate`, and `bahtera barem -> Bahtera Barem` are exact display aliases only. Normalized prediction display fields are cache/read-time values and do not add database columns.

2026-07-18 Stock Planner detail safety note: no database schema, status, or record changes. Pending quantity is calculated from existing active `purchase_orders` and `purchase_order_items`; the detail page links to the latest matching active PO without creating new data.

## External Stateless Services

### `prediction-service`

Purpose: Provides local FastAPI stock action predictions for the Smart Stock Planner MVP.

Database behavior:

- Does not create or modify database tables.
- Does not read Ting Hao database records directly.
- Receives inventory, usage, expiry, pending PO, and supplier lead-time values from the caller.
- Returns a JSON recommendation that Laravel can store or act on in a later integration phase.
- Laravel currently stores prediction results in cache only and has not added a `stock_predictions` table.
- Laravel currently stores Qwen stock prediction explanations in cache only and has not added a stock explanation audit table.
- English-only Qwen stock explanations use a versioned cache key so older mixed-language explanation cache entries are bypassed and can be replaced.
- Stock Planner Calendar View is generated from cached/generated prediction results and does not add calendar tables.
- Stock Planner prediction cache keys are per ingredient and use the configured Laravel cache store. Normal page loads reuse cached arrays until `STOCK_PREDICTION_CACHE_MINUTES` expires; forced refresh overwrites one ingredient cache entry.
- Eligible add-stock predictions can create existing Laravel workflow records: `agent_runs`, `agent_tool_calls`, `agent_reasoning_steps`, `purchase_orders`, `purchase_order_items`, and `approval_requests`.

Notes:

- The service is rule-based and stateless in this phase.
- Qwen is not used for raw forecasting calculations.
- Future Laravel integration should keep purchase order creation, approvals, and audit logs inside the Laravel database workflow.
- Current Laravel integration summarizes existing `stock_movements` and `purchase_order_items` data before calling FastAPI; it does not send full tables.
- Qwen receives only the FastAPI prediction result and compact Laravel facts; it does not read database tables directly.
- Qwen is not called during restock PO draft creation; cached Qwen explanation JSON can be attached to the agent run context when already available.

## Tables

### `users`

Purpose: Stores admin and staff login accounts.

Important fields:

- `name`, `email`, `password`
- `role`: `admin` or `staff`
- `status`: active/inactive account state
- `email_verified_at`, `remember_token`

Relationships:

- Creates ingredients, stock movements, restock requests, purchase orders, purchase order demos, and backup records.

Notes:

- Passwords are hashed by Laravel.
- Role checks are handled by route middleware and model helpers.

### `categories`

Purpose: Groups inventory ingredients.

Important fields:

- `name`
- `description`

Relationships:

- Has many `ingredients`.

### `ingredients`

Purpose: Stores bakery ingredient inventory records.

Important fields:

- `category_id`, `supplier_id`
- `name`, `sku`, `unit`
- `quantity`, `minimum_stock`
- `cost_price`, `selling_price`
- `expiry_date`, `notes`
- `created_by`, `updated_by`

Relationships:

- Belongs to `categories`, `suppliers`, and users.
- Has many `stock_movements`, `stock_allocations`, `supplier_returns`, `restock_requests`, `purchase_order_items`, `expiry_loss_recommendations`, and optional `purchase_order_demo_items`.

Notes:

- Low stock is calculated when `quantity <= minimum_stock`.
- Expiry pages use `expiry_date`.

### `stock_movements`

Purpose: Audits stock in and stock out changes.

Important fields:

- `ingredient_id`
- `type`: `in` or `out`
- `quantity`, `quantity_before`, `quantity_after`
- `reason`, `notes`
- `created_by`

Relationships:

- Belongs to `ingredients` and users.

Notes:

- The TingHao Agent restock planning button does not create a `restock_requests` row; it creates existing agent run, pending PO draft, PO item, and approval request records through the agent workflow.

Notes:

- Stock out should not create negative ingredient quantity.
- Real purchase order receiving writes stock-in movement records only for accepted quantity.
- Damaged, returned, and shortage quantities are tracked through purchase order items and supplier returns, not as accepted stock.

### `restock_requests`

Purpose: Tracks low-stock restock workflow records.

Important fields:

- `ingredient_id`
- `requested_quantity`
- `status`
- `notes`
- `created_by`, `updated_by`

Relationships:

- Belongs to `ingredients` and users.

### `suppliers`

Purpose: Stores supplier contact and sourcing information.

Important fields:

- `name`
- `contact_person`
- `phone`
- `email`
- `address`
- `notes`

Relationships:

- Has many `ingredients`, `purchase_orders`, `supplier_email_drafts`, and `supplier_returns`.

### `purchase_orders`

Purpose: Stores real purchase order records connected to suppliers and ingredients.

Important fields:

- `po_number`
- `supplier_id`
- `agent_run_id`
- `status`: draft/sent-style purchase order state
- `order_date`, `expected_delivery_date`
- `subtotal`
- `notes`
- `agent_reasoning`
- `email_to`, `sent_at`
- `confirmed_at`, `received_at`, `closed_at`
- `requested_by`, `approved_by`
- `created_by`

Relationships:

- Belongs to `suppliers` and users.
- Optionally belongs to `agent_runs`.
- Has many `purchase_order_items`.
- Has many `supplier_email_drafts`.
- Has many `stock_allocations` and `supplier_returns`.
- Has one `approval_request`.

Notes:

- The Phase 3 supplier email draft workflow saves and marks email drafts sent without sending a real email.
- Agent-created PO drafts start as `pending_approval`.
- Stock Prediction restock PO drafts also start as `pending_approval` and include `notes` showing they were created from FastAPI Stock Prediction.
- Admin approval changes agent-created drafts to `approved`; rejection changes them to `rejected`.
- Duplicate open PO prevention checks existing PO items for the same ingredient across draft, pending approval, approved, sent, confirmed, and partially received statuses.
- Receiving is allowed only when `purchase_orders.status` is `confirmed` or `partially_received`.
- The confirm action can move `draft`, `approved`, or `sent` purchase orders to `confirmed`; `pending_approval` still requires approval first.
- Receiving accepted stock updates ingredient quantity and creates stock-in movement records.
- Damaged/returned receiving records create `supplier_returns`.
- Shortage and damaged quantities are visible on PO detail and dashboard alert counters.
- Full GRN document generation is not implemented yet.

### `supplier_email_drafts`

Purpose: Stores Qwen-generated supplier email drafts for approved purchase orders.

Important fields:

- `purchase_order_id`
- `supplier_id`
- `agent_run_id`
- `subject`
- `body`
- `status`: `draft`, `approved`, or `sent`
- `approved_by`
- `approved_at`
- `sent_at`
- `qwen_model`
- `qwen_metadata` JSON

Relationships:

- Belongs to `purchase_orders`.
- Optionally belongs to `suppliers`.
- Optionally belongs to `agent_runs`.
- Optionally belongs to the approving user.

Notes:

- Drafts are admin-reviewed before mark-sent.
- Mark-sent is a demo-safe state change and does not use SMTP, Gmail, WhatsApp, or external messaging.
- Existing drafts are reused to avoid duplicate Qwen calls unless an admin explicitly regenerates while the draft is still in `draft` status.
- `qwen_metadata` stores safe server-side model, mock mode, HTTP status, latency, token, max-token, and temperature metadata only; it does not store API keys or raw chain-of-thought.
- Application code checks that optional `approved_at`, `qwen_model`, and `qwen_metadata` columns exist before writing, so draft generation works even if the migration has not yet been applied.

### `expiry_loss_recommendations`

Purpose: Stores TingHao Agent expiry loss prevention recommendations.

Important fields:

- `agent_run_id`
- `ingredient_id`
- `quantity_at_risk`
- `unit`
- `cost_price`
- `potential_loss`
- `expiry_date`
- `days_until_expiry`
- `recommendation_title`
- `recommendation_body`
- `status`: `active`, `reviewed`, `dismissed`, or `completed`
- `reviewed_by`

Relationships:

- Belongs to `ingredients`.
- Optionally belongs to `agent_runs`.
- Optionally belongs to the reviewing user.

Notes:

- Recommendations are generated only for non-expired ingredients expiring within 7 days.
- Potential loss is calculated as quantity multiplied by cost price when cost price exists.
- Missing cost price leaves RM impact null.

### `approval_requests`

Purpose: Stores human review checkpoints for agent-created purchase order drafts.

Important fields:

- `agent_run_id`
- `purchase_order_id`
- `type`: currently `purchase_order`
- `status`: `pending`, `approved`, or `rejected`
- `requested_by`, `reviewed_by`
- `review_notes`

Relationships:

- Belongs to `agent_runs`.
- Belongs to `purchase_orders`.
- Belongs to requested/reviewed users.

### `purchase_order_items`

Purpose: Stores line items for real purchase orders.

Important fields:

- `purchase_order_id`
- `ingredient_id`
- `description`
- `quantity`, `unit`, `unit_price`, `line_total`
- `received_quantity`
- `accepted_quantity`
- `damaged_quantity`
- `returned_quantity`
- `shortage_quantity`
- `quality_status`
- `receiving_notes`

Relationships:

- Belongs to `purchase_orders` and `ingredients`.
- Has many `stock_allocations` and `supplier_returns`.

Notes:

- `received_quantity` is updated when stock is received from the real PO workflow.
- `accepted_quantity` is the only receiving field that increases `ingredients.quantity`.
- The redesigned receiving page changes presentation only and does not add database fields.
- Valid quality statuses are `accepted`, `partially_accepted`, `damaged`, `rejected`, `shortage`, and `returned`.

### `stock_locations`

Purpose: Stores named places where received stock can be allocated.

Important fields:

- `name`
- `type`: `storage`, `production`, `front`, or `quarantine`
- `notes`
- `is_active`

Seeded locations:

- Store Room
- Production Area
- Front Counter
- Quarantine / Damaged

Runtime behavior:

- The purchase order receiving flow creates/reactivates these four standard rows if they are missing so the allocation form is never empty.

Relationships:

- Has many `stock_allocations`.

### `stock_allocations`

Purpose: Audits how received PO quantities were split across physical stock locations.

Important fields:

- `ingredient_id`
- `stock_location_id`
- `purchase_order_id`
- `purchase_order_item_id`
- `quantity`
- `movement_type`: default `in`
- `notes`
- `created_by`

Relationships:

- Belongs to `ingredients`, `stock_locations`, `purchase_orders`, `purchase_order_items`, and users.

Notes:

- Usable allocations to Store Room, Production Area, and Front Counter must equal accepted quantity.
- The receiving worksheet defaults normal accepted stock to the active Store Room location; this is UI behavior only and does not change the allocation schema.
- Quarantine / Damaged allocation is for damaged goods physically kept before return.

### `supplier_returns`

Purpose: Tracks damaged or returned stock that should be handled with suppliers.

Important fields:

- `purchase_order_id`
- `purchase_order_item_id`
- `supplier_id`
- `ingredient_id`
- `return_number`
- `damaged_quantity`
- `returned_quantity`
- `reason`
- `status`: `pending`, `sent_to_supplier`, `resolved`, or `rejected_by_supplier`
- `created_by`

Relationships:

- Belongs to `purchase_orders`, `purchase_order_items`, `suppliers`, `ingredients`, and users.

Notes:

- Created during PO receiving when damaged or returned quantity is recorded.
- Admin can update supplier return status.
- Staff can view supplier returns but cannot resolve or reject them.

### `purchase_order_demos`

Purpose: Stores presentation-ready purchase order demo workflow records.

Important fields:

- `po_number`
- `supplier_name`, `supplier_email`
- `status`: `draft`, `email_sent`, `supplier_confirmed`, `partially_received`, `received`, `closed`
- `order_date`, `expected_delivery_date`
- `subtotal`
- `email_sent_at`, `confirmed_at`, `received_at`, `closed_at`
- `notes`, `created_by`

Relationships:

- Belongs to users.
- Has many `purchase_order_demo_items`.

Notes:

- This workflow is intended for demonstration and presentation.
- Receiving demo PO items can update real ingredient stock when an item is linked to an ingredient.

### `purchase_order_demo_items`

Purpose: Stores line items and receiving progress for demo purchase orders.

Important fields:

- `purchase_order_demo_id`
- `ingredient_id`
- `ingredient_name`
- `quantity`, `unit`, `unit_price`, `line_total`
- `received_quantity`
- `quality_status`

Relationships:

- Belongs to `purchase_order_demos`.
- Optionally belongs to `ingredients`.

### `system_settings`

Purpose: Stores configurable system and shop settings.

Important fields:

- Setting key/value fields used by the system settings page.

Relationships:

- None required for current scope.

### `backup_records`

Purpose: Stores backup snapshot audit records.

Important fields:

- Snapshot count and metadata fields for system counts.
- `created_by`

Relationships:

- Belongs to users when created by an authenticated admin.

Notes:

- These records are audit snapshots, not full database backup files.

### `agent_runs`

Purpose: Stores each TingHao Agent procurement message run and Stock Prediction restock autopilot task.

Important fields:

- `user_id`
- `input_text`, `input_type`
- `status`
- `parsed_intent` JSON
- `final_summary`
- `qwen_mocked`

Relationships:

- Belongs to `users`.
- Has many `agent_tool_calls`.
- Has many `agent_reasoning_steps`.
- Has many `supplier_email_drafts`.
- Has many `expiry_loss_recommendations`.

Notes:

- Admin can review all runs.
- Staff can review only runs they created.
- `parsed_intent` stores parser output plus matched inventory and supplier context for UI review.
- Stock Prediction restock runs use `input_type = stock_prediction_restock` and store `source = stock_planner`, FastAPI prediction snapshot, cached Qwen explanation when available, selected supplier, safe timeline labels, and linked `purchase_order_id` in `parsed_intent`.

### `agent_tool_calls`

Purpose: Stores visible internal tool activity for each agent run.

Important fields:

- `agent_run_id`
- `tool_name`
- `input_payload` JSON
- `output_payload` JSON
- `status`

Relationships:

- Belongs to `agent_runs`.
- Has many `agent_reasoning_steps` through `related_tool_call_id`.

Notes:

- Tool names include procurement parsing, inventory lookup, supplier lookup, restock planning, supplier ranking, stock prediction reading, restock action validation, PO draft creation, approval request creation, supplier email draft generation, draft approval, mark-sent audit records, expiry scans, expiry loss calculation, expiry recommendation generation, and recommendation saving.

### `agent_reasoning_steps`

Purpose: Stores safe explainability steps for agent runs without raw chain-of-thought.

Important fields:

- `agent_run_id`
- `step_order`
- `step_type`: `observe`, `understand`, `plan`, `tool_action`, `tool_result`, `decision`, `risk_check`, `human_checkpoint`, or `final_summary`
- `title`
- `summary`
- `evidence` JSON
- `confidence`
- `risk_level`: `low`, `medium`, `high`, or `blocked`
- `requires_human_approval`
- `related_tool_call_id`

Relationships:

- Belongs to `agent_runs`.
- Optionally belongs to `agent_tool_calls`.

Notes:

- Stores concise visible summaries only.
- Does not store hidden model chain-of-thought.

### Laravel Framework Tables

Purpose: Support Laravel authentication, sessions, cache, and queues.

Tables:

- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`

## Current Database Limitations

- No POS sales tables are implemented.
- No product recipe mapping tables are implemented.
- No dedicated permission tables are implemented.
- No full GRN table is implemented.
- Backup snapshots are metadata records, not full exported backups.
- TingHao Agent creates pending-approval PO drafts, supplier email drafts, and expiry loss recommendations, but does not send real supplier messages.
- Embedded autopilot entry points reuse existing agent audit, PO, supplier email draft, and expiry recommendation tables; no separate autopilot tables are introduced.
- Dashboard status badges, PO business summaries, and supplier email safety notes are presentation-layer changes only and do not require new fields.
- Qwen token metadata and cache behavior do not require schema changes. Safe Qwen metadata can be recorded in existing `agent_tool_calls.output_payload`; parser cache entries live in the configured Laravel cache store.
- Stock prediction cache hit/miss/refresh logging is application logging only and does not add database tables.
- Staff-scoped Dashboard and Agent Audit PO/email visibility uses existing ownership fields: `purchase_orders.requested_by`, `approval_requests.requested_by`, `supplier_email_drafts.purchase_order_id`, and the linked purchase order owner. No permission or join table was added.
- Final demo link and permission polish did not add tables or columns. Staff/admin UI differences are computed from existing `users.role` and ownership fields.

## Performance Indexes

Added on 2026-06-28:

- `ingredients_stock_level_index` on `ingredients(quantity, minimum_stock)`
- `ingredients_category_name_index` on `ingredients(category_id, name)`
- `ingredients_supplier_name_index` on `ingredients(supplier_id, name)`
- `stock_movements_created_at_index` on `stock_movements(created_at)`
- `stock_movements_type_created_at_index` on `stock_movements(type, created_at)`
- `restock_requests_status_created_at_index` on `restock_requests(status, created_at)`
- `purchase_orders_status_created_at_index` on `purchase_orders(status, created_at)`

Notes:

- These indexes support dashboard summaries, low-stock/expiry/report filters, stock history ordering, and purchase order status lists.

## Dashboard Cache Data Shape

Updated on 2026-06-29:

- No schema changes were made.
- Dashboard recent movement rows are read from `stock_movements` with related `ingredients` and `users`, then cached as scalar arrays.
- Cached movement fields used by the UI: `id`, `type`, `quantity`, `ingredient_name`, `creator_name`, and `created_at`.
- Today's Autopilot Actions are not stored in a table. They are generated from current inventory, purchase order, supplier email draft, and expiry recommendation records.

## Pagination UI Note

Updated on 2026-06-29:

- No database schema changes were made for pagination icon sizing.
- Existing paginated queries continue to use Laravel pagination against the real tables.

## Phase 5 Demo Readiness Note

Updated on 2026-06-29:

- No database schema changes were made for Phase 5.
- Demo data continues to use `DatabaseSeeder` with `updateOrCreate`.
- Demo records include admin/staff users, Supplier Ali, sugar/gula, flour, milk, low-stock examples, near-expiry examples, supplier email/phone values, and stock movement history.

## Autopilot Command Center UI Note

Updated on 2026-06-29:

- No database schema changes were made for the command-center UI upgrade.
- The UI reuses existing `agent_runs`, `agent_tool_calls`, `agent_reasoning_steps`, `purchase_orders`, `supplier_email_drafts`, and `expiry_loss_recommendations` records.
# Maintenance note (2026-07-18)

No database schema, table, field, relationship, or status value changed. This update only adjusts shared header layout spacing.

## Purchase Orders Index QA Note (2026-07-18)

- No schema, migration, relationship, or stored status value changed.
- The UI now presents existing `purchase_orders.status` values as workflow guidance: `pending_approval`, `approved`, `sent`, `confirmed`, `partially_received`, `received`, `closed`, `rejected`, and `cancelled`.
- Stock Prediction source display continues to derive from the existing `purchase_orders.agent_run_id` relationship and `agent_runs.input_type = stock_prediction_restock`.

## Purchase Order Suggestions Data Note (2026-07-18)

- No schema or migration changes were made.
- Delivery suggestions read `purchase_orders.supplier_id`, `order_date`, `received_at`, `closed_at`, and terminal `received`/`closed` statuses.
- Price suggestions read `purchase_order_items.ingredient_id` and `unit_price`, excluding rejected/cancelled POs before falling back to `ingredients.cost_price`.
- Quantity suggestions read `ingredients.quantity`, `minimum_stock`, and the existing Laravel Stock Planner cache entry. Suggestions are not persisted separately.
- The current `suppliers` table has no lead-time column; completed PO history or the two-day fallback is used.

## Purchase Order Detail Workflow State Note (2026-07-18)

- No database schema or status value changed.
- Existing `purchase_orders.status` values drive the manual or Agent timeline; `agent_run_id` / `approval_requests` identify approval-based workflows.
- `supplier_email_drafts.status` (`draft`, `approved`, `sent`) drives the Agent email checkpoints.

## Goods Receiving UI Alignment Note (2026-07-18)

- No schema, relationship, or persisted status value changed for the receiving usability update.
- `purchase_order_items.quality_status` still accepts `accepted`, `partially_accepted`, `damaged`, `rejected`, `shortage`, and `returned`.
- The worksheet's default quality selection mirrors the existing server inference; the submitted value is stored normally.
- `received_quantity = accepted_quantity + damaged_quantity + shortage_quantity`. `returned_quantity` remains separate supplier-return tracking and cannot exceed damaged quantity.
- Only `accepted_quantity` increases `ingredients.quantity`; the existing stock movement, allocation, and supplier return records are unchanged.
- The allowed confirmation transition is now `sent` → `confirmed`; draft and approved statuses cannot be confirmed directly.
- Receiving remains `confirmed` → `partially_received|received`, and close remains `received` → `closed`.

## Purchase Order Timeline Evidence Note (2026-07-18)

- No schema, migration, relationship, or status value changed.
- Manual Email Sent completion reads existing `purchase_orders.sent_at`.
- A related `supplier_email_drafts.status = sent` record is accepted as a demo-safe marked-sent state and is labelled accordingly; it does not imply real email delivery.
- Existing `confirmed_at`, `received_at`, `closed_at`, and `purchase_orders.status` values continue to drive later manual workflow steps.
- Manual `approved_by = null` remains unchanged in storage and is presented as `Not applicable` in the detail UI.

## Run-Aware Agent Visualizer Data Note (2026-07-18)

- No table, field, relationship, status value, or migration changed.
- Procurement visualizer nodes read existing `agent_runs`, `agent_tool_calls`, `purchase_orders`, `approval_requests`, and `supplier_email_drafts` records.
- Expiry visualizer nodes read existing `agent_runs`, `agent_tool_calls`, and `expiry_loss_recommendations` records, including recommendation review status.
- Run type is inferred at display time from `agent_runs.input_type` and `parsed_intent.intent`; no workflow type is persisted separately.
- Missing legacy audit evidence remains null/absent and is displayed as `Not recorded` rather than creating synthetic records.

## Responsive UI Data Note (2026-07-18)

- No migration, table, column, index, relationship, cast, status value, seed record, or persistence behavior changed.
- Responsive cards, contained table/calendar scrolling, and stacked forms render the same existing Dashboard, ingredient, prediction cache, purchase order, receiving, supplier email draft, and agent audit records.
- No responsive state is stored in the database.

## Phase 1 Email Delivery And Audit Schema (2026-07-18)

Migration: `2026_07_18_000001_add_delivery_audit_to_supplier_email_drafts.php`.

New nullable `supplier_email_drafts` fields:

- `delivery_status`: `delivered`, `failed`, or `demo_marked_sent` when an action has occurred.
- `delivery_provider`: safe provider label such as `gmail_smtp` or `demo_safe`.
- `delivery_metadata`: JSON containing safe result, attempt time, optional provider message ID, or non-secret error code.
- `last_delivery_attempt_at`: timestamp of the latest real or demo delivery action.

An index covers `delivery_status` and `last_delivery_attempt_at`. Gmail username/password and API credentials are never copied into database records.

Phase 1 reuses existing relationships: `AgentRun -> AgentToolCall/AgentReasoningStep/PurchaseOrder`, `PurchaseOrder -> ApprovalRequest/SupplierEmailDraft/PurchaseOrderItem`, and receiving allocations/returns. Supplier comparison is computed from existing PO item prices, PO dates/statuses, receiving quantities, and supplier contact fields; no supplier score table was added. Predictions and Qwen explanations remain cache/read-time data, while the prediction snapshot and comparison used for an Agent PO are stored in `agent_runs.parsed_intent`.

## Delivery Audit Migration Compatibility (2026-07-19)

- `supplier_email_drafts` deployments created before `2026_07_18_000001_add_delivery_audit_to_supplier_email_drafts.php` may lack all four nullable delivery-audit fields.
- Application writes now detect those absent optional columns and continue with the existing draft status, approval, sent timestamp, and PO state fields only.
- This is a temporary compatibility guard, not a schema replacement: run the migration to store delivery result/provider/metadata/attempt timestamps and use the delivery-audit index.

## Bounded Restock Decision Audit Data (2026-07-19)

- No schema or migration changed.
- `agent_runs.input_type = stock_prediction_restock` identifies the bounded restock mission. `status` becomes `needs_approval` only after a pending PO draft is created; safe terminal branches remain `completed`, while iteration-limit failure uses `failed`.
- `agent_runs.parsed_intent.decision_loop` stores `maximum_iterations`, compact iteration records, and final `stop_reason`. Each record contains observation, selected action, compact tool result, safe reason summary, confidence, decision source, and stop reason.
- Existing `agent_tool_calls` store `qwen_select_next_action` metadata and each Laravel-executed allowed tool. Existing `agent_reasoning_steps` store decision summaries, tool results, final summary, and the human checkpoint.
- Qwen raw response text, prompts, chain-of-thought, API keys, and secrets are not stored. Existing PO, approval, supplier, ingredient, and stock schemas are unchanged.
# Agent Audit Visualizer Data Sources (2026-07-19)

- No schema change was required.
- `agent_runs` supplies mission identity, owner, status, timestamps, Qwen mock/live mode, and safe parsed intent.
- `agent_reasoning_steps` supplies safe observation, decision, confidence, risk, and human-checkpoint summaries. `related_tool_call_id` prevents duplicate timeline rows.
- `agent_tool_calls` supplies executed tool name, status, and compact safe result summaries. Raw payloads are not rendered by the `/agent` visualizer.
- `approval_requests` supplies pending/approved/rejected state, reviewer, review notes, and update time.
- `purchase_orders`, `supplier_email_drafts`, and `expiry_loss_recommendations` supply final business outcomes and links.
- Historical absence is displayed as skipped/not recorded; the visualizer does not synthesize missing audit facts.

## Agent Audit Milestone Grouping (2026-07-19)

- No schema change. Seven UI milestones group existing `agent_tool_calls` by tool name and `agent_reasoning_steps` by step type/related tool.
- Agent mission status remains `agent_runs.status`; procurement status is read separately from `purchase_orders.status`, `approval_requests.status`, expiry recommendation status, or a stored decision-loop stop reason.
- Tool payloads remain stored unchanged but are not rendered in the default milestone view.
