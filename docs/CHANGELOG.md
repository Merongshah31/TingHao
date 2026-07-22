# Ting Hao Changelog

Record every system update in chronological order. Each entry should be simple enough for future developers and stakeholders to understand what changed.

## 2026-07-20 - Resend Test Recipient Separation

### Summary

Resend test mode now sends only to the configured test recipient while preserving the linked supplier email as the intended business recipient in PO and audit context. Production mode continues to send to the linked supplier.

### Files Changed

- app/Services/Mail/ResendSupplierMailService.php
- tests/Feature/ResendSupplierEmailTest.php
- Required documentation files in docs/

### Routes Added Or Changed

- None.

### Database Changes

- None. Existing delivery metadata and audit payloads now distinguish masked intended and actual recipients.

### Permission Changes

- None.

### Known Limitations

- Test mode uses the configured Resend test recipient and does not represent supplier inbox delivery.

### Next Steps

- Keep Resend test mode enabled until the production sender domain is verified.

## 2026-07-19 - Bounded Restock Stop Validation

### Summary

Validated Qwen stop actions against Laravel mission state. Nonterminal iterations now expose only the required next action, and premature stops are audited as rejected before deterministic fallback.

### Files Changed

- app/Services/Agent/PredictionRestockPlanningService.php
- tests/Feature/RestockDecisionLoopTest.php
- tests/Feature/AutopilotPhaseOneTest.php
- Required documentation files in docs/

### Routes Added Or Changed

- None.

### Database Changes

- None. Existing agent tool calls and reasoning records store rejected-action metadata and the safe reason.

### Permission Changes

- None.

### Known Limitations

- Qwen decisions may still be replaced by deterministic Laravel actions when invalid or unavailable.

### Next Steps

- Keep duplicate-PO, expiry, and approval checkpoint tests in the regression suite.

## 2026-07-19 - Resend Test Isolation Verification

### Summary

Updated feature-test configuration so demo Mark Sent and Resend test-mode UI assertions do not depend on local `.env` values. Production email guards and high-confidence autopilot behavior were unchanged.

### Files Changed

- `tests/Feature/AgentConsoleTest.php`
- `tests/Feature/AutopilotPhaseOneTest.php`
- `tests/Feature/PurchaseOrderManagementTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- None.

### Database Changes

- None. The legacy optional delivery-audit migration compatibility test still removes only its optional columns and verifies status and timestamps.

### Permission Changes

- None. Admin-only Resend and demo Mark Sent guards remain unchanged.

### Known Limitations

- Automated tests use faked mail/provider boundaries and do not verify external inbox delivery.

### Next Steps

- Run one controlled manual Resend test-mode send after deployment configuration is reviewed.

## 2026-07-19 - Resend Supplier Email Delivery

### Summary

Integrated explicit real supplier email sending through Resend for approved supplier email drafts. Admins can send only after PO approval and draft approval; test mode permits only the configured test recipient, records Resend acceptance metadata, and prevents duplicate sends with a lock plus provider idempotency key. Demo-safe Mark Sent remains available only when real email delivery is disabled.

### Files Changed

- `.env.example`
- `config/autopilot.php`
- `config/services.php`
- `app/Models/SupplierEmailDraft.php`
- `app/Services/Mail/ResendSupplierMailService.php`
- `app/Http/Controllers/SupplierEmailDraftController.php`
- `app/Http/Controllers/PurchaseOrderController.php`
- `routes/web.php`
- `resources/views/supplier-email-drafts/show.blade.php`
- `resources/views/purchase-orders/show.blade.php`
- `database/migrations/2026_07_19_000001_add_resend_fields_to_supplier_email_drafts_table.php`
- `tests/Feature/ResendSupplierEmailTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- Added admin-only `POST /supplier-email-drafts/{supplierEmailDraft}/send-resend` (`supplier-email-drafts.send-resend`).
- Existing supplier draft and PO detail pages now show Resend send actions when real email is enabled and demo Mark Sent only when real email is disabled.

### Database Changes

- Added nullable `supplier_email_drafts.provider`, `provider_message_id`, `sent_by`, and `send_error_category`.
- Reuses existing `status`, `sent_at`, `approved_by`, `approved_at`, `qwen_model`, `qwen_metadata`, `delivery_status`, `delivery_provider`, `delivery_metadata`, and `last_delivery_attempt_at`.

### Permission Changes

- No role model changes. The Resend route is authenticated and admin-only.
- Staff cannot send supplier email drafts through Resend.

### Known Limitations

- Resend acceptance is recorded as `accepted`; inbox delivery is not claimed until a future delivery webhook confirms it.
- In `RESEND_TEST_MODE=true`, the linked supplier email must match `RESEND_TEST_RECIPIENT`.

### Next Steps

- Run the new migration in deployed environments.
- Configure `RESEND_API_KEY`, keep `REAL_EMAIL_ENABLED=true` only after review, and send one controlled test draft to `bakerytinghao@outlook.com`.
- Add webhook handling later if delivery/bounce/open status should update `delivery_status` after Resend acceptance.

## 2026-07-19 - Agent Audit Milestone Simplification

### Summary

Made the selected live mission the primary `/agent` view, collapsed the static Phase 1 map under `How TingHao Autopilot Works`, and grouped visible audit evidence into seven clickable business milestones with one Selected Step Details panel. Actor badges identify Qwen, FastAPI, Laravel, human approval, and system audit evidence only when supported by the selected run. Individual tool calls remain in collapsed Technical Audit Details.

### Files Changed

- `app/Http/Controllers/AgentController.php`
- `resources/views/agent/index.blade.php`
- `public/css/tinghao.css`
- `tests/Feature/AgentConsoleTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- No routes added or renamed. Existing `GET /agent?run={agentRun}` renders the simplified selected-run milestone view.

### Database Changes

- None. Milestones group existing audit records at read time.

### Permission Changes

- None. Admin can inspect all runs; staff remain scoped to their own runs.

### Known Limitations

- Actor badges and optional details appear only where the historical run contains supporting tools or reasoning records.

### Next Steps

- Verify one live FastAPI restock mission and one expiry mission at desktop and mobile widths before the final demo.

## 2026-07-19 - Agent Audit Visualizer

### Summary

Replaced the duplicated Template/Live workflow presentation on `/agent` with one selected-run audit visualizer. It shows a real-record run summary, eight-stage governed workflow strip, chronological tool/reasoning timeline, human checkpoint, final persisted outcomes, and a collapsed technical index without exposing API keys, raw payloads, or chain-of-thought. Expiry missions mark procurement outcomes as not used rather than implying those actions ran.

### Files Changed

- `app/Http/Controllers/AgentController.php`
- `resources/views/agent/index.blade.php`
- `public/css/tinghao.css`
- `tests/Feature/AgentConsoleTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- No route was added or renamed.
- Existing `GET /agent?run={agentRun}` now selects a permitted run and renders its consolidated audit visualizer.
- Recent mission links now select the run inside `/agent`; `GET /agent/runs/{agentRun}` remains the full technical audit.

### Database Changes

- None. The visualizer reads existing `agent_runs`, `agent_reasoning_steps`, `agent_tool_calls`, `approval_requests`, `purchase_orders`, `supplier_email_drafts`, and `expiry_loss_recommendations` records.

### Permission Changes

- None. Admin can inspect all runs; staff can select and inspect only their own runs through the existing controller scope.

### Known Limitations

- Older runs can show `Not recorded` or `Not used in this run` when their historical workflow did not persist a corresponding event.
- Approval edits are shown only when an existing run stored `approval.changed_fields`; no changes are inferred.

### Next Steps

- Exercise one restock run and one expiry run with representative demo data and confirm their timeline summaries remain concise.

## 2026-07-19 - Bounded Qwen Restock Decision Loop

### Summary

Added one Qwen-selected, Laravel-executed decision loop to Stock Planner restock missions. The loop is limited to four decisions, validates every selected action against a state-specific allowlist, returns compact tool observations to Qwen, branches safely for duplicate POs and expired stock, and stops draft creation at the existing admin approval checkpoint. Malformed, unavailable, timed-out, or invalid Qwen output uses a deterministic safe fallback. Agent Audit now shows each compact observation, selected action, result, safe reason, and stop reason.

### Files Changed

- `.env.example`, `config/qwen.php`
- `app/Services/Agent/PredictionRestockPlanningService.php`
- `app/Http/Controllers/AgentController.php`
- `resources/views/agent/show.blade.php`
- `tests/Feature/RestockDecisionLoopTest.php`, `tests/Feature/StockPlannerPredictionTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- No routes added or renamed.
- Existing `POST /stock-planner/ingredient/{ingredient}/plan-restock` now runs the bounded decision loop after loading the cached/current FastAPI prediction.
- Existing `GET /agent/runs/{agentRun}` displays the compact loop audit when the run contains decision-loop evidence.

### Database Changes

- No migration or schema change. Safe loop iterations are stored in existing `agent_runs.parsed_intent`; selected actions/results use existing `agent_tool_calls` and `agent_reasoning_steps`.

### Permission Changes

- None. Existing authenticated restock access remains unchanged; staff still cannot approve POs, and admin approval remains mandatory.

### Known Limitations

- The loop applies only to Stock Planner restock missions and makes at most four Qwen decisions.
- Qwen mock mode or unavailable/malformed responses use deterministic fallback decisions and are labelled as such in Agent Audit.

### Next Steps

- Run the three documented demo branches with live Qwen configured, then verify admin approval remains the first critical human action.

## 2026-07-19 - Supplier Email Draft Legacy Schema Compatibility

### Summary

Prevented supplier-email draft editing and demo-safe marking as sent from failing when an existing deployment has not yet applied the nullable delivery-audit migration. Core draft, approval, PO sent-state, and audit workflow continue to work; delivery evidence is persisted once the migration is applied.

### Files Changed

- `app/Http/Controllers/SupplierEmailDraftController.php`
- `app/Services/Agent/SupplierEmailDeliveryService.php`
- `tests/Feature/AutopilotPhaseOneTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- No routes added or renamed.
- Existing `PUT /supplier-email-drafts/{supplierEmailDraft}`, `POST /supplier-email-drafts/{supplierEmailDraft}/mark-sent`, and `POST /supplier-email-drafts/{supplierEmailDraft}/send-via-gmail` now skip unavailable optional delivery-audit fields on legacy schemas.

### Database Changes

- No new migration. `2026_07_18_000001_add_delivery_audit_to_supplier_email_drafts.php` remains required to persist delivery status, provider, metadata, and attempt time.

### Permission Changes

- None. Draft edit, approval, mark-sent, and Gmail delivery remain admin-only.

### Known Limitations

- A deployment that has not run the delivery-audit migration cannot retain delivery evidence until it is migrated.

### Next Steps

- Run `php artisan migrate --force` in the deployed environment, then confirm the supplier-email draft edit and marked-sent/Gmail paths.

## 2026-07-18 - Phase 1 Autopilot Inventory and Procurement

### Summary

Completed the confirmed Phase 1 gaps for the Observe -> Predict -> Decide -> Human Approve -> Act -> Verify -> Audit workflow. Added an hourly, deduplicated inventory/expiry scan that reuses FastAPI prediction cache and never calls Qwen; evidence-based supplier comparison; optional high-confidence approval-gated PO drafts; admin-editable supplier email drafts; optional explicit Gmail delivery; delivery success/failure persistence; receiving and closure audit records; and a live Phase 1 capability map on `/agent` and `/demo`.

### Files Changed

- `.env.example`, `config/autopilot.php`, `render.yaml`
- `routes/console.php`, `routes/web.php`
- `app/Console/Commands/TingHaoAutopilotScan.php`
- `app/Http/Controllers/AgentController.php`, `PurchaseOrderController.php`, `SupplierEmailDraftController.php`
- `app/Mail/SupplierEmailDraftMail.php`, `app/Models/SupplierEmailDraft.php`
- `app/Services/Agent/AgentWorkflowAuditService.php`, `AutopilotInventoryScanService.php`, `PhaseOneCapabilityService.php`, `PredictionRestockPlanningService.php`, `SupplierComparisonService.php`, `SupplierEmailDeliveryService.php`
- `database/migrations/2026_07_18_000001_add_delivery_audit_to_supplier_email_drafts.php`
- `resources/views/components/agent/phase-one-capability-map.blade.php`, `resources/views/emails/supplier-email-draft.blade.php`
- `resources/views/agent/index.blade.php`, `demo.blade.php`, `purchase-orders/show.blade.php`, `stock-planner/show.blade.php`, `supplier-email-drafts/show.blade.php`
- `public/css/tinghao.css`, `tests/Feature/AutopilotPhaseOneTest.php`, `tests/Feature/DemoReadinessTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- Added admin-only `PUT /supplier-email-drafts/{supplierEmailDraft}` for reviewed subject/body edits.
- Added admin-only `POST /supplier-email-drafts/{supplierEmailDraft}/send-via-gmail` for explicit approved delivery.
- Registered hourly `php artisan tinghao:autopilot-scan` through Laravel Scheduler.
- Existing PO approval, confirmation, receiving, close, `/agent`, and `/demo` routes now expose or record Phase 1 evidence without changing route names.
- `/demo` instructions now follow the Stock Planner/autopilot flow instead of the retired procurement message-entry UI.

### Database Changes

- Added nullable `delivery_status`, `delivery_provider`, `delivery_metadata`, and `last_delivery_attempt_at` fields plus a delivery audit index to `supplier_email_drafts`.
- No credentials are stored in delivery metadata. Existing PO, approval, AgentRun, and email status values remain unchanged.

### Permission Changes

- Staff can continue to view permitted predictions, request restock planning, receive permitted goods, and view their linked records.
- Staff cannot edit/approve/send supplier drafts, approve/reject POs, confirm suppliers, or close POs.
- Admin remains the required human for PO approval/rejection, email edit/approval, explicit Gmail send or demo Mark Sent, supplier confirmation, and close.

### Known Limitations

- Production must run Laravel Scheduler separately; the web service alone does not execute hourly scans.
- Real Gmail requires an app password and valid SMTP environment. It remains disabled by default.
- Supplier ranking can only compare evidence present in existing PO and receiving history; missing evidence is displayed as `Insufficient history`.
- Automatic PO drafts remain disabled by default and are never approved or emailed automatically.

### Next Steps

- Run the migration, configure a scheduler worker/cron, and perform one controlled Gmail test only after setting `REAL_EMAIL_ENABLED=true`.
- Complete the documented admin/staff browser demo using representative supplier history and receiving discrepancies.

## 2026-07-18 - Purchase Order detail workflow state QA

### Summary

Replaced the Purchase Order detail page's two conflicting timelines with one origin-aware workflow. Manual POs now show Draft through Closed; Agent/Stock Prediction POs show the admin approval and supplier email draft checkpoints. Added one status-specific Next step panel, removed generic pre-confirmation receiving controls, made terminal/rejected states action-free, clarified approval wording, and aligned supplier confirmation with the `sent` status only.

### Files Changed

- `app/Models/PurchaseOrder.php`
- `resources/views/purchase-orders/show.blade.php`
- `resources/views/supplier-email-drafts/show.blade.php`
- `public/css/tinghao.css`
- `tests/Feature/PurchaseOrderManagementTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- No routes added or renamed.
- Existing `POST /purchase-orders/{purchaseOrder}/confirm` now accepts only `sent` POs through the corrected model helper; draft and approved POs receive HTTP 422.

### Database Changes

- No migrations, fields, relationships, or stored status values changed.

### Permission Changes

- No role expansion. Admin-only approval, rejection, email generation/approval/mark-sent, confirmation, and close controls remain hidden from staff. Valid receiving remains available to authorized admin/staff users.

### Known Limitations

- The legacy manual `send-email` action remains the existing explicit supplier-email action; automatic email is not triggered by page load.
- Final browser screenshot QA is still recommended for long PO item and supplier names.

### Next Steps

- Walk through one manual and one Stock Prediction PO from creation to close while recording the final demo.

## 2026-07-18 - Create Purchase Order smart suggestions

### Summary

Added advisory smart suggestions to the existing Create Purchase Order form. Supplier and order-date changes estimate delivery from completed PO history with a two-day fallback; ingredient changes read cached Stock Planner quantity, previous PO prices, and ingredient cost data. Suggestions remain editable, line totals still update locally, and no PO, approval, FastAPI request, or Qwen request is triggered automatically.

### Files Changed

- `app/Http/Controllers/PurchaseOrderController.php`
- `routes/web.php`
- `resources/views/purchase-orders/create.blade.php`
- `resources/views/purchase-orders/partials/form.blade.php`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- `public/css/tinghao.css`
- `tests/Feature/PurchaseOrderManagementTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- Added `GET /purchase-orders/suggestions` as `purchase-orders.suggestions` with existing `auth` and `role:admin` protection.
- Existing Purchase Order create, store, edit, approval, and receiving routes are unchanged.

### Database Changes

- No migrations or stored fields were added.
- Suggestions read existing ingredient, supplier, purchase order, purchase-order item, and Laravel cache data.

### Permission Changes

- No role expansion. The suggestion endpoint and Create Purchase Order page are admin-only; staff receive HTTP 403.

### Known Limitations

- The current supplier schema has no default lead-time field, so the endpoint uses completed PO history and then the documented two-day fallback. It will recognize `default_lead_time_days` or `lead_time_days` if such an attribute is added later.
- Suggestions are request-time advisory values and are not stored as separate audit records.

### Next Steps

- Browser-check supplier/date and ingredient changes with representative history and long ingredient names.

## 2026-07-18 - Purchase Orders index final demo QA

### Summary

Polished the Purchase Orders index without changing its workflow. The action column now uses a compact stacked group inside a normal table cell, lower-priority columns collapse responsively, and mobile rows become readable cards without horizontal scrolling. Replaced the blanket receiving hint with status-specific next steps, kept the stock-prediction source badge compact, shortened the low-stock create label, and preserved admin/staff action visibility.

### Files Changed

- `resources/views/purchase-orders/index.blade.php`
- `public/css/tinghao.css`
- `resources/lang/en/messages.php`
- `tests/Feature/PurchaseOrderManagementTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- No routes were added or changed.
- Verified the existing Purchase Order index, detail, create, edit, approval, supplier communication, confirmation, receiving, and close routes.

### Database Changes

- No database changes or migrations.

### Permission Changes

- No permission changes. Staff still see only POs they requested and do not see admin-only edit, approval, supplier email, confirmation, or close controls. Admin users retain the existing workflow actions.

### Known Limitations

- Final browser screenshot QA is still recommended with production-like long supplier and user names.
- Terminal PO edits remain available through existing backend routes to admins, but the index no longer advertises edit actions for rejected, received, closed, or cancelled rows.

### Next Steps

- Capture final admin and staff screenshots of `/purchase-orders` at laptop and mobile widths.

## 2026-07-18 - Stock Planner detail safety QA

### Summary

Aligned the Stock Planner detail page with current expiry and pending-PO facts. Expired items now show a blocking warning and Expiry action, items with pending purchase quantity show the existing PO link instead of a duplicate restock button, and the server-side planning service independently blocks expired or already-pending restock attempts. Renamed the collapsed audit section to Technical Audit Details and added judge/developer safety wording.

### Files Changed

- `app/Http/Controllers/StockPlannerController.php`
- `app/Services/Agent/PredictionRestockPlanningService.php`
- `resources/views/stock-planner/show.blade.php`
- `resources/views/stock-planner/index.blade.php`
- `public/css/tinghao.css`
- `tests/Feature/StockPlannerPredictionTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- No routes added or changed.
- Existing prediction detail, plan-restock, purchase-order detail, purchase-order list, and expiry routes are reused.

### Database Changes

- No database changes.

### Permission Changes

- No permission changes. Existing authenticated Stock Planner access and admin PO approval restrictions remain unchanged.

### Known Limitations

- Pending PO links select the latest active PO containing the ingredient; if only an aggregate pending quantity is available, the UI falls back to the Purchase Orders list.

### Next Steps

- Capture the final detail screenshot for expired, pending-PO, and valid restock examples.

## 2026-07-18 - Stock Planner final visual and data QA

### Summary

Hardened FastAPI and Laravel prediction handling so add-stock actions always have a positive fallback quantity, expired and near-expiry conditions receive clear priority labels, and non-purchase actions show business advice instead of zero purchase quantities. Limited Calendar View to two priority badges per day and four compact secondary links, gated restock buttons by action, quantity, and supplier availability, and added display-only English aliases for selected demo units and names.

### Files Changed

- `prediction-service/main.py`
- `prediction-service/test_main.py`
- `prediction-service/README.md`
- `app/Services/Stock/StockPredictionApiClient.php`
- `app/Services/Stock/StockPredictionInputBuilder.php`
- `app/Services/Agent/PredictionRestockPlanningService.php`
- `app/Http/Controllers/StockPlannerController.php`
- `app/Support/QuantityFormatter.php`
- `app/Support/StockPlannerDisplay.php`
- `resources/views/stock-planner/index.blade.php`
- `resources/views/stock-planner/show.blade.php`
- `public/css/tinghao.css`
- `tests/Feature/StockPlannerPredictionTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- No routes added or changed.
- Existing Stock Planner cards, calendar, prediction detail, refresh, explain, and plan-restock routes retain their names and methods.

### Database Changes

- No schema or stored-data changes. Demo name and unit corrections are display-only aliases.

### Permission Changes

- No permission changes. Existing authenticated admin/staff access and human approval rules remain in place.

### Known Limitations

- Display name aliases cover only the explicitly identified demo typos.
- The FastAPI service must be restarted after prediction rule source changes when reload mode is not active.

### Next Steps

- Capture final Prediction View, Calendar View, and prediction detail screenshots using representative expired, near-expiry, add-stock, and no-purchase records.

## 2026-07-18 - Dashboard final visual and data QA

### Summary

Hardened the dashboard Stock Prediction Signals copy so add-stock actions use a positive fallback quantity when cached FastAPI data contains zero, while non-purchase actions show business-friendly wording instead of `Suggested: 0.00`. Clarified the approved-PO email-draft badge and restored the Purchase Orders and TingHao Agent Management Center icons using the existing Lucide set.

### Files Changed

- `app/Http/Controllers/DashboardController.php`
- `resources/views/dashboard.blade.php`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- `tests/Feature/DashboardTest.php`
- Required documentation files in `docs/`

### Routes Added Or Changed

- No routes added or changed.
- Existing admin and staff dashboard routes were checked.

### Database Changes

- No database changes.

### Permission Changes

- No permission changes. Existing admin/global and staff/own-record Autopilot Action scopes remain unchanged.

### Known Limitations

- Dashboard prediction signals remain dependent on cached Stock Planner predictions.

### Next Steps

- Perform one final browser check at desktop and narrow viewport widths with representative cached prediction records.

## 2026-07-08 - Agent Audit UI Simplification

### Summary

Simplified `/agent` into a judge/admin audit page by removing the obsolete Smart Procurement Inbox message form and the old Visible Activity workflow panel from the UI. The Autopilot Workflow Visualizer now becomes the main section after the summary cards, while recent agent missions, proof links, expiry loss entry point, audit routes, backend agent run action, parser services, Qwen services, AgentRun models, and embedded Stock Planner/Low Stock restock workflows remain preserved.

### Files Changed

- `resources/views/agent/index.blade.php`
- `tests/Feature/AgentConsoleTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No routes added or removed.
- Existing `/agent`, `/agent/run`, `/agent/runs/{agentRun}`, `/agent/proof`, Stock Planner, Low Stock, PO, and supplier email workflows remain available.

### Database Changes

- No database changes.

### Permission Changes

- No permission rule changes.
- Admin/staff visibility remains scoped by the existing AgentController behavior.

### Known Limitations

- Browser screenshot QA is still recommended to confirm the visualizer spacing after removing the old two-column inbox/workflow block.

### Next Steps

- Open `/agent` as admin and staff to confirm the page reads as an audit/visualizer surface and not a message-entry page.

## 2026-07-08 - Agent Workflow Visualizer

### Summary

Added an Autopilot Workflow Visualizer to `/agent` so hackathon judges can see TingHao Autopilot as a connected business pipeline. The visualizer includes Template View for the static system map and Live Run View for the latest or selected AgentRun, with colored node statuses, human-in-the-loop labeling on Admin Approval, tool labels, and click-to-view business details without exposing API keys or raw chain-of-thought.

### Files Changed

- `app/Http/Controllers/AgentController.php`
- `resources/views/agent/index.blade.php`
- `public/css/tinghao.css`
- `tests/Feature/AgentConsoleTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No routes added or removed.
- `GET /agent` now accepts an optional `run` query parameter to choose which visible AgentRun feeds the Live Run View.
- Existing `/agent`, `/agent/run`, `/agent/runs/{agentRun}`, and `/agent/proof` behavior remains available.

### Database Changes

- No database schema changes.
- The visualizer reads existing `agent_runs`, `agent_tool_calls`, `purchase_orders`, `approval_requests`, and `supplier_email_drafts` records.

### Permission Changes

- No permission rule changes.
- Admin can visualize all runs. Staff can visualize only their own AgentRun records, matching existing `/agent` access behavior.

### Known Limitations

- The visualizer is read-only and summarizes the latest visible records; it does not create new audit records.
- Browser screenshot QA is still recommended before final demo recording.

### Next Steps

- Demo `/agent` in both Template View and Live Run View after creating a fresh restock mission, approving the PO, generating the email draft, and marking it sent.

## 2026-07-08 - Sidebar Navigation Hardening

### Summary

Adjusted only the existing TingHao dashboard sidebar. The sidebar now keeps a consistent desktop width, avoids horizontal scrolling, scrolls vertically only inside the menu area, uses compact grouped navigation, shows Autopilot Actions as a clickable icon navigation item, keeps the dark-green theme, and retains drawer behavior on smaller screens.

### Files Changed

- `resources/views/components/dashboard/sidebar.blade.php`
- `public/css/tinghao.css`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No routes added or removed.
- Existing sidebar links continue to use the current dashboard, inventory, stock, Stock Planner, low-stock, expiry, supplier, purchase order, reports, help, agent audit, settings, and backup routes.

### Database Changes

- No database changes.

### Permission Changes

- No permission changes.
- Admin-only settings and backup links remain visible only for admin users.

### Known Limitations

- Browser screenshot QA is still recommended across common desktop and mobile widths before final demo recording.

### Next Steps

- Check the sidebar in a live browser at desktop, tablet, and mobile widths to confirm there is no horizontal scrollbar and drawer behavior remains smooth.

## 2026-07-05 - Hackathon Demo QA Wording And Verification

### Summary

Ran the final hackathon demo QA pass for the Stock Planner, FastAPI prediction service, Qwen explanation, purchase order approval, supplier email draft, Dashboard Autopilot Actions, proof endpoints, and permission tests. Tightened the Stock Planner Qwen language filter for common Malay connector/action words, kept explanations English-only, verified the local FastAPI prediction service health and prediction POST, verified Laravel's `StockPredictionApiClient` can call the live service, added core inventory/report smoke coverage, and renamed remaining visible demo links from "Agent Console" to "Agent Audit" so the technical page reads as an audit/proof surface instead of the normal daily workflow.

### Files Changed

- `app/Services/Agent/StockPredictionReasoningService.php`
- `.gitignore`
- `resources/views/demo.blade.php`
- `resources/views/agent/expiry-loss.blade.php`
- `tests/Feature/CoreInventoryDemoFlowTest.php`
- `tests/Feature/DemoReadinessTest.php`
- `tests/Feature/StockPlannerPredictionTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No routes added or removed.
- Rechecked Stock Planner, purchase order, supplier email draft, `/agent`, `/agent/proof`, `/health`, and `/demo` routes.

### Database Changes

- No database schema changes.
- Generated Python virtual environment files are local development artifacts only and are not part of the Laravel database workflow.

### Permission Changes

- No permission rule changes.
- Existing tests still verify staff cannot approve/reject POs or generate/approve/mark supplier email drafts, while admin can.

### Known Limitations

- Browser screenshot QA is still recommended before recording the final demo video.
- Live Qwen English wording should still be spot-checked with the real key before the hackathon recording.

### Next Steps

- Record the final walkthrough after confirming the local Laravel app and FastAPI prediction service are both running.
- Latest automated QA evidence: `php artisan test` passed with 69 tests and 579 assertions; live FastAPI `/health`, live FastAPI prediction POST, Laravel `StockPredictionApiClient`, `/health`, `/agent/proof`, and `/demo` were checked locally.

## 2026-07-05 - English-Only Stock Planner Qwen Explanations

### Summary

Updated Stock Planner Qwen explanation behavior for the hackathon demo. The stock prediction explanation prompt now requires clear professional English only, JSON-only output, no markdown, no raw chain-of-thought, no recalculation, no invented customer/demand/sales facts, and no Malay or mixed Malay-English wording. Clicking the explanation action now regenerates the cached explanation in English so old mixed-language cache entries can be replaced. Mock/fallback explanations are English-only and use only provided FastAPI prediction facts.

### Files Changed

- `app/Services/Agent/StockPredictionReasoningService.php`
- `app/Http/Controllers/StockPlannerController.php`
- `resources/views/stock-planner/show.blade.php`
- `tests/Feature/StockPlannerPredictionTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No routes added or removed.
- `POST /stock-planner/ingredient/{ingredient}/explain` now regenerates the English explanation for the current FastAPI prediction snapshot.

### Database Changes

- No database schema changes.
- Qwen stock explanations remain stored in Laravel cache only.

### Permission Changes

- No permission changes.
- Admin and staff can still request Stock Planner Qwen explanations.

### Known Limitations

- The service rejects common Malay/mixed-language words and falls back to English text, but ingredient names are still displayed as stored in inventory.
- Qwen is still called only when the user explicitly clicks the Stock Planner explanation action.

### Next Steps

- In live Qwen mode, click Generate/Regenerate English Explanation on a few ingredients and confirm all visible explanation fields are professional English.

## 2026-07-05 - Stock Planner Prediction Cache Optimization

### Summary

Optimized Stock Planner prediction calls so normal page refreshes reuse cached FastAPI prediction results within `STOCK_PREDICTION_CACHE_MINUTES`. Prediction View loads predictions only for the visible card page, Calendar View no longer preloads Prediction View card predictions, Dashboard continues to read cached signals only, and the Refresh Prediction action now explicitly forces one selected ingredient. Local environment logging records stock prediction cache hits, misses, and forced refreshes without calling Qwen or exposing secrets.

### Files Changed

- `app/Http/Controllers/StockPlannerController.php`
- `tests/Feature/StockPlannerPredictionTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No routes added or removed.
- Rechecked `/stock-planner?view=cards`, `/stock-planner?view=calendar`, `/stock-planner/ingredient/{ingredient}/prediction`, `POST /stock-planner/ingredient/{ingredient}/refresh-prediction`, and `POST /stock-planner/ingredient/{ingredient}/plan-restock`.

### Database Changes

- No database schema changes.
- Prediction results remain stored in Laravel cache only; no `stock_predictions` table was added.

### Permission Changes

- No permission changes.
- Admin and staff can still view predictions and force-refresh one ingredient prediction.

### Known Limitations

- First load can still call FastAPI for ingredients without cached predictions.
- Calendar View still prioritizes up to the first 50 ingredients for signal generation.
- Local cache hit/miss logs are written only in the local environment.

### Next Steps

- In a live browser, open Stock Planner twice and confirm the second load is mostly cache hits in local logs.
- Confirm production cache configuration is stable before demo/deployment.

## 2026-07-04 - Final Hackathon Demo Link And Permission Polish

### Summary

Ran a final focused QA hardening pass on the TingHao hackathon demo workflow. Staff-facing Dashboard Autopilot PO cards now use Review wording instead of Approve, the Dashboard Human Review / Agent Approvals shortcut is admin-only, the Stock Planner dashboard shortcut uses current Stock Planner wording instead of old calendar-demo language, and the Agent mission detail generates supplier email drafts through the canonical `/generate-email-draft` route.

### Files Changed

- `app/Http/Controllers/DashboardController.php`
- `resources/views/dashboard.blade.php`
- `resources/views/agent/show.blade.php`
- `tests/Feature/DashboardTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No routes added or removed.
- Rechecked Stock Planner cards/calendar/detail/plan-restock, purchase order index/detail/approve/reject/email draft generation, supplier email draft show/approve/mark-sent, Agent Audit, `/agent/proof`, `/health`, and `/demo`.

### Database Changes

- No database schema changes.

### Permission Changes

- Staff no longer see the Dashboard Human Review / Agent Approvals shortcut.
- Staff pending PO Autopilot cards now link to review only; admin pending PO cards continue to use approval wording.
- Admin-only PO approval, rejection, supplier email draft approval, and mark-sent actions remain protected by existing controller authorization.

### Known Limitations

- Browser screenshot QA was not run in this pass; verification used route checks, code review, and focused feature tests.
- Legacy `/stock-memory-demo` and `/calendar-demo` routes remain as redirects for compatibility, but daily UI wording now points to Stock Planner.

### Next Steps

- Run one live browser walkthrough before recording the final hackathon demo.
- Confirm production-like databases have the supplier email draft Qwen metadata migration applied before showing those fields live.

## 2026-07-04 - Hackathon Demo QA Hardening Pass

### Summary

Ran a focused QA hardening pass on the hackathon demo flow covering Stock Planner, restock planning, PO approval, supplier email drafts, Dashboard Autopilot Actions, Agent Audit, proof endpoints, and staff/admin visibility. Staff Dashboard and Agent Audit summary cards now scope PO/email draft approval counts and action cards to records the staff user is allowed to open. The Agent Audit email-draft status card now links to Dashboard Autopilot Actions instead of looping back to the same page.

### Files Changed

- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/AgentController.php`
- `resources/views/agent/index.blade.php`
- `tests/Feature/DashboardTest.php`
- `tests/Feature/AgentConsoleTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No routes added or removed.
- Rechecked Stock Planner, purchase order approval, supplier email draft, Dashboard, Agent Audit, `/agent/proof`, `/health`, and `/demo` routes.

### Database Changes

- No database schema changes.

### Permission Changes

- Staff Dashboard Autopilot Actions now show only PO/email draft actions tied to purchase orders requested by that staff user.
- Staff Agent Audit summary counts now scope pending PO approvals, email drafts waiting approval, and recent missions to the staff user.
- Admin views remain global.

### Known Limitations

- Browser screenshot QA was not run in this pass; verification used route checks and feature tests.
- Staff can still view shared expiry-loss risk totals, matching existing expiry recommendation visibility.

### Next Steps

- Run a live browser walkthrough before recording the hackathon demo.
- Confirm Supabase migrations are applied before showing Qwen metadata fields in production-like demo data.

## 2026-07-04 - Qwen Supplier Email Draft After PO Approval

### Summary

Hardened the supplier email draft workflow after purchase order approval. Admins can now use `POST /purchase-orders/{purchaseOrder}/generate-email-draft` to call Qwen with compact PO, supplier, item, and business-context facts, save a draft for review, and redirect to the supplier email draft page. Existing drafts are reused without another Qwen call, draft regeneration is an explicit admin action, Qwen outage no longer saves a fake draft unless mock mode is enabled, and supplier email drafts now store Qwen model/metadata plus approval timestamp when the database migration has been applied.

### Files Changed

- `.env.example`
- `config/qwen.php`
- `database/migrations/2026_07_04_000001_add_qwen_metadata_to_supplier_email_drafts_table.php`
- `app/Models/SupplierEmailDraft.php`
- `app/Services/Agent/SupplierEmailDraftService.php`
- `app/Http/Controllers/SupplierEmailDraftController.php`
- `app/Http/Controllers/DashboardController.php`
- `routes/web.php`
- `resources/views/purchase-orders/show.blade.php`
- `resources/views/supplier-email-drafts/show.blade.php`
- `tests/Feature/AgentConsoleTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added `POST /purchase-orders/{purchaseOrder}/generate-email-draft` (`purchase-orders.generate-email-draft`).
- Kept existing `POST /purchase-orders/{purchaseOrder}/email-draft` (`purchase-orders.email-draft`) as a compatibility alias.
- Added `POST /supplier-email-drafts/{supplierEmailDraft}/regenerate` (`supplier-email-drafts.regenerate`).
- Existing supplier email draft show, approve, and mark-sent routes remain unchanged.

### Database Changes

- Added nullable `approved_at`, `qwen_model`, and `qwen_metadata` columns to `supplier_email_drafts`.
- The code checks for these optional columns before writing so existing databases do not 500 before the migration is run.
- No new supplier email draft table was created because it already existed.

### Permission Changes

- Admin can generate, regenerate, approve, and mark supplier email drafts sent.
- Staff can view permitted drafts only and cannot generate, regenerate, approve, or mark sent.
- Qwen does not approve POs, approve drafts, mark sent, or update the database directly.

### Known Limitations

- Mark Sent remains demo-safe and does not send SMTP/Gmail/WhatsApp messages.
- Regenerate uses a normal admin POST action; a browser confirmation modal can be added later if desired.
- Qwen email draft cache config is present, but draft reuse is enforced by the saved draft record rather than a separate cache entry.
- Qwen model/metadata and approval timestamp are omitted gracefully until the database migration is applied.

### Next Steps

- Browser-check PO detail, supplier email draft review, regenerate, approve, and mark-sent states.
- Add an edit-before-approve UI if admins need to manually revise draft content in-app.

## 2026-07-04 - Stock Prediction Restock Autopilot Workflow

### Summary

Connected Stock Planner prediction results to the existing TingHao Agent purchase order approval workflow. Predictions with `add_stock_now` or `add_stock_soon` can now create a `pending_approval` PO draft from `/stock-planner/ingredient/{ingredient}/plan-restock`. Laravel validates the prediction action, prevents duplicate open POs for the same ingredient, selects a supplier, creates the PO draft and approval request, and writes agent audit/tool-call records. Non-buy actions remain advisory and do not create purchase orders.

### Files Changed

- `app/Http/Controllers/StockPlannerController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Services/Agent/PredictionRestockPlanningService.php`
- `routes/web.php`
- `resources/views/stock-planner/show.blade.php`
- `resources/views/purchase-orders/index.blade.php`
- `resources/views/purchase-orders/show.blade.php`
- `tests/Feature/StockPlannerPredictionTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added `POST /stock-planner/ingredient/{ingredient}/plan-restock` (`stock-planner.plan-restock`) for admin/staff restock planning from FastAPI prediction results.
- Stock Planner Calendar View and detail page now post to the new Stock Planner route instead of the low-stock message parser route.
- Purchase Order list/detail pages now show when a PO was created from Stock Prediction.

### Database Changes

- No schema changes.
- The workflow writes existing `agent_runs`, `agent_tool_calls`, `agent_reasoning_steps`, `purchase_orders`, `purchase_order_items`, and `approval_requests` records.
- Stock prediction and Qwen explanation snapshots remain cache/JSON context only; no `stock_predictions` table was added.

### Permission Changes

- Admin and staff can request a restock plan from eligible Stock Planner predictions.
- Staff-created drafts remain `pending_approval`; staff cannot approve or reject purchase orders.
- Admin approval/rejection remains enforced through existing PO routes and `HumanApprovalGuardService`.

### Known Limitations

- Supplier category fallback is inferred from other ingredients in the same category because suppliers do not have a dedicated category/status field.
- Admin quantity editing still uses the existing PO edit page.
- Qwen is not called during PO creation; only cached explanation text is attached when already available.

### Next Steps

- Browser-check the Stock Planner detail, Calendar View, PO list badge, and PO approval card.
- Consider a dedicated persisted stock prediction snapshot table if long-term audit history becomes required.

## 2026-07-04 - Unified Stock Planner Cards And Calendar

### Summary

Unified the FastAPI-backed prediction cards and the old static calendar demo into one user-facing Stock Planner module. `/stock-planner` now supports Prediction View and Calendar View through the `view` query parameter, and Calendar View is generated from the same prediction cache/results used by the cards. The old `/stock-memory-demo` and `/calendar-demo` routes redirect to `/stock-planner?view=calendar`. Quantity display now uses a formatter that avoids duplicated or numeric unit noise.

### Files Changed

- `app/Http/Controllers/StockPlannerController.php`
- `app/Http/Controllers/StockMemoryDemoController.php`
- `app/Services/Agent/StockPredictionReasoningService.php`
- `app/Support/QuantityFormatter.php`
- `app/Http/Controllers/DashboardController.php`
- `routes/web.php`
- `resources/views/stock-planner/index.blade.php`
- `resources/views/stock-planner/show.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- `public/css/tinghao.css`
- `tests/Feature/StockPlannerPredictionTest.php`
- `tests/Feature/StockMemoryDemoTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- `/stock-planner` supports `?view=cards` and `?view=calendar`.
- `/stock-memory-demo` now redirects to `/stock-planner?view=calendar`.
- Added `/calendar-demo`, which redirects to `/stock-planner?view=calendar`.
- Existing prediction detail, refresh prediction, and explain routes remain unchanged.

### Database Changes

- No database schema changes.
- Calendar View uses cached/generated FastAPI prediction results, not a new table.

### Permission Changes

- No permission changes.
- Admin and staff can access both Stock Planner views.
- Qwen is still not called by Dashboard, Calendar View, or Prediction View cards.

### Known Limitations

- Calendar View uses cached/generated predictions for the first 50 prioritized ingredients.
- Month navigation remains scoped to the current month in this phase.

### Next Steps

- Add browser screenshot QA for Prediction View, Calendar View, and the date advice panel.
- Consider a persisted `stock_predictions` table if long-term calendar history is needed.

## 2026-07-04 - Qwen Stock Prediction Explanation Layer

### Summary

Added Qwen Cloud reasoning/explanation support for FastAPI stock prediction results. Stock Planner detail now shows a Qwen Explanation section with short business-friendly wording, recommended next step, warning, confidence label, and safe audit metadata. Qwen receives only compact prediction facts, does not calculate forecasts, does not receive stock history tables, and is cached by ingredient plus prediction snapshot hash.

### Files Changed

- `.env.example`
- `config/qwen.php`
- `app/Services/Qwen/QwenClient.php`
- `app/Services/Agent/StockPredictionReasoningService.php`
- `app/Http/Controllers/StockPlannerController.php`
- `routes/web.php`
- `resources/views/stock-planner/index.blade.php`
- `resources/views/stock-planner/show.blade.php`
- `public/css/tinghao.css`
- `tests/Feature/StockPlannerPredictionTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added Laravel `POST /stock-planner/ingredient/{ingredient}/explain` (`stock-planner.explain`).
- Existing Stock Planner prediction, refresh, Dashboard, Agent, Inventory, and Purchase Order routes remain unchanged.

### Database Changes

- No database schema changes.
- Qwen explanation results are stored in Laravel cache only.

### Permission Changes

- Admin and staff can request/view Qwen explanations on Stock Planner detail.
- Admin approval remains required for any restock/PO workflow.
- No stock, PO, approval, or inventory mutation is performed by Qwen.

### Known Limitations

- Qwen explanations are not persisted for long-term audit beyond cache.
- Dashboard prediction signals intentionally do not call Qwen.
- Qwen unavailable state shows a friendly fallback while FastAPI prediction remains visible.

### Next Steps

- Decide whether Qwen stock explanations need a database-backed audit table.
- Add browser screenshot QA for Stock Planner Qwen Explanation and Advanced Details panels.
- Keep Qwen limited to explanation text; forecasting remains in FastAPI.

## 2026-07-02 - Laravel Stock Prediction Integration

### Summary

Connected Laravel Ting Hao to the local FastAPI Stock Prediction Service. Laravel now builds compact per-ingredient stock summaries, calls `POST /predict-stock-action`, caches the result, shows predictions in a new Stock Planner page, supports force-refresh per ingredient, and surfaces cached important prediction signals on the Dashboard. Qwen is not used for prediction in this phase, and no purchase orders are created automatically.

### Files Changed

- `.env.example`
- `config/stock_prediction.php`
- `app/Services/Stock/StockPredictionApiClient.php`
- `app/Services/Stock/StockPredictionInputBuilder.php`
- `app/Http/Controllers/StockPlannerController.php`
- `app/Http/Controllers/DashboardController.php`
- `routes/web.php`
- `resources/views/stock-planner/index.blade.php`
- `resources/views/stock-planner/show.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/components/dashboard/sidebar.blade.php`
- `public/css/tinghao.css`
- `tests/Feature/StockPlannerPredictionTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added Laravel `GET /stock-planner` (`stock-planner.index`).
- Added Laravel `GET /stock-planner/ingredient/{ingredient}/prediction` (`stock-planner.prediction`).
- Added Laravel `POST /stock-planner/ingredient/{ingredient}/refresh-prediction` (`stock-planner.refresh-prediction`).
- Existing `/stock-memory-demo` remains available as the calendar demo.
- Existing FastAPI `GET /health` and `POST /predict-stock-action` remain in `prediction-service`.

### Database Changes

- No database schema changes.
- Predictions are cached with Laravel cache using `STOCK_PREDICTION_CACHE_MINUTES`.
- No `stock_predictions` table was added in this phase.

### Permission Changes

- Admin and staff can open the Stock Planner and refresh predictions.
- Admin and staff can still use the existing safe TingHao Agent restock planning button when a prediction recommends adding stock.
- Staff permission restrictions for approvals and purchase orders are unchanged.

### Known Limitations

- Dashboard shows cached prediction signals only; open Stock Planner first to generate or refresh predictions.
- Prediction service outages show a friendly unavailable message and do not break Laravel pages.
- No Qwen explanations, Alibaba Cloud PAI, ML training, production authentication, or automatic PO creation are included yet.

### Next Steps

- Add production service authentication/network restrictions before exposing the FastAPI service beyond local use.
- Decide whether prediction snapshots need a database audit table.
- Add Qwen later only for business-friendly explanation text after deterministic prediction results are returned.

## 2026-07-02 - Stock Prediction Service MVP

### Summary

Added the first lightweight FastAPI Stock Prediction Service for Ting Hao Smart Stock Planner. The new service is separate from Laravel and provides deterministic rule-based stock action recommendations for buy now, buy soon, buy less, do not buy, monitor, and use before expiry decisions. Laravel remains responsible for UI, database records, purchase orders, approvals, and audit logs.

### Files Changed

- `prediction-service/main.py`
- `prediction-service/requirements.txt`
- `prediction-service/README.md`
- `prediction-service/Dockerfile`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added FastAPI `GET /health` inside `prediction-service`.
- Added FastAPI `POST /predict-stock-action` inside `prediction-service`.
- No Laravel route paths were added or changed.

### Database Changes

- No database schema changes.
- The prediction service is stateless and does not read or write Ting Hao tables directly.

### Permission Changes

- No Laravel permission changes.
- The service has no production authentication layer yet and is intended for local MVP use before Laravel integration.

### Known Limitations

- Rule-based MVP only; no ML model is trained.
- Laravel UI and workflows are not connected to the prediction service yet.
- Qwen, Alibaba Cloud PAI, paid services, and automatic purchase order creation are intentionally not used in this phase.

### Next Steps

- Add Laravel-side integration after the API contract is reviewed.
- Add automated tests for prediction edge cases and optional service authentication before deployment.
- Use Qwen later only to explain prediction results in business-friendly language.

## 2026-07-02 - Agent Audit Navigation And Qwen Token Efficiency

### Summary

Refined TingHao Agent so normal users see "Autopilot Actions" in daily navigation while `/agent` is labeled as an Agent Audit Console for proof, debugging, and judging. Added purpose-specific Qwen token limits, shared temperature, parser result caching by normalized input hash, safe Qwen metadata, live/mock mode display, and batched expiry recommendation generation so deterministic Laravel work does not spend extra Qwen tokens.

### Files Changed

- `config/qwen.php`
- `.env.example`
- `app/Services/Qwen/QwenClient.php`
- `app/Services/Agent/ProcurementMessageParserService.php`
- `app/Services/Agent/SupplierEmailDraftService.php`
- `app/Services/Agent/ExpiryLossPreventionService.php`
- `app/Http/Controllers/AgentController.php`
- `resources/views/agent/index.blade.php`
- `resources/views/components/dashboard/sidebar.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/purchase-orders/show.blade.php`
- `resources/views/supplier-email-drafts/show.blade.php`
- `resources/views/expiry-loss-recommendations/show.blade.php`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- `public/css/tinghao.css`
- `tests/Feature/AgentConsoleTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No route paths were added or changed.
- Existing `/agent` remains available as the Agent Audit Console.
- Existing dashboard, low-stock, inventory, PO, supplier email draft, and expiry workflow routes remain unchanged.

### Database Changes

- No schema changes.
- Safe Qwen metadata is stored only inside existing agent tool-call payloads when relevant.

### Permission Changes

- No permission changes.
- Staff still cannot approve PO drafts, approve supplier email drafts, mark email drafts sent, run expiry scans, or complete expiry recommendations.

### Known Limitations

- Live token counts depend on Qwen returning usage fields in the API response.
- Browser screenshot QA is still recommended for the renamed sidebar and audit console cards.

### Next Steps

- Verify live deployment with `QWEN_MOCK_MODE=false`, a server-side API key, and the new Qwen token limit env values.

## 2026-07-02 - TingHao Agent Embedded Autopilot Polish

### Summary

Tightened the embedded TingHao Agent workflow UI so daily pages read more clearly as business checkpoints. Dashboard autopilot cards now show a visible status badge; agent-created purchase order details show suggested item quantities and a plain "why this order is suggested" summary before technical audit details; and supplier email drafts now state that no real email is sent automatically and that admin controls the final action.

### Files Changed

- `resources/views/dashboard.blade.php`
- `resources/views/purchase-orders/show.blade.php`
- `resources/views/supplier-email-drafts/show.blade.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No route paths were added or changed.
- Existing `/agent` audit console routes remain available.
- Existing `POST /alerts/low-stock/{ingredient}/agent-plan` remains the embedded restock planning entry point.

### Database Changes

- No schema changes.
- Existing agent run, PO, approval request, and supplier email draft records are reused.

### Permission Changes

- No permission changes.
- Admin-only approval/rejection and email approval/mark-sent routes remain protected.
- Staff can request agent restock planning but still cannot approve restricted actions.

### Known Limitations

- Browser screenshots should still be captured for final visual QA at desktop and mobile widths.
- Production Qwen behavior still depends on server-side Qwen configuration.

### Next Steps

- Recheck Dashboard, Purchase Order detail, and Supplier Email Draft detail in a live browser to confirm the status badges and business summaries fit cleanly.

## 2026-07-01 - TingHao Agent Workflow Integration

### Summary

Refactored TingHao Agent from a console-first experience into an embedded autopilot layer across daily workflow pages. Dashboard now shows "Today's Autopilot Actions"; low-stock and inventory detail pages can ask the agent to plan restock using existing ingredient and supplier data; expiry tracking shows the highest expiry-loss prevention recommendation; and normal PO, supplier email draft, and expiry recommendation pages hide technical reasoning details behind Advanced Details while keeping `/agent` as the audit console.

### Files Changed

- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/LowStockController.php`
- `app/Http/Controllers/ExpiryController.php`
- `app/Services/Agent/ProcurementMessageParserService.php`
- `routes/web.php`
- `resources/views/dashboard.blade.php`
- `resources/views/alerts/low-stock.blade.php`
- `resources/views/inventory/show.blade.php`
- `resources/views/expiry/index.blade.php`
- `resources/views/purchase-orders/show.blade.php`
- `resources/views/supplier-email-drafts/show.blade.php`
- `resources/views/expiry-loss-recommendations/show.blade.php`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- `tests/Feature/AgentConsoleTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added `POST /alerts/low-stock/{ingredient}/agent-plan` (`alerts.restock.agent-plan`) for admin and staff to trigger a TingHao Agent restock plan from an existing low-stock ingredient.
- Existing `/agent` routes remain available as the Agent Console and audit/judge view.

### Database Changes

- No schema changes.
- The new low-stock agent action creates existing `agent_runs`, `agent_tool_calls`, `agent_reasoning_steps`, pending-approval `purchase_orders`, `purchase_order_items`, and `approval_requests` records through the existing agent services.

### Permission Changes

- Admin and staff can request an agent restock plan from low-stock workflows.
- Admin approval is still required for agent-created purchase order drafts, supplier email draft approval/mark-sent, and expiry recommendation completion.

### Known Limitations

- The workflow button uses the existing procurement parser and mock-mode fallback; production Qwen behavior still depends on server-side Qwen configuration.
- No real supplier email is sent automatically.

### Next Steps

- Recheck Dashboard, Low Stock, Inventory detail, Purchase Order detail, Supplier Email Draft, and Expiry pages in a live browser to confirm the embedded cards fit at common desktop and mobile widths.

## 2026-07-01 - Goods Receiving Status Gate And Allocation Location Fix

### Summary

Fixed the purchase order goods receiving flow so invalid PO statuses no longer expose a raw Laravel 422 page. Receive actions now appear only for confirmed or partially received POs; other statuses show a disabled “Receive available after PO confirmed” hint. Direct `/purchase-orders/{purchaseOrder}/receive` access redirects back to the PO detail with a validation message. The confirm action now supports safe pre-receiving statuses, and the receive form self-heals missing stock allocation locations by creating/reactivating Store Room, Production Area, Front Counter, and Quarantine / Damaged.

### Files Changed

- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Models/PurchaseOrder.php`
- `resources/views/purchase-orders/index.blade.php`
- `resources/views/purchase-orders/show.blade.php`
- `resources/views/purchase-orders/receive.blade.php`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- `tests/Feature/PurchaseOrderManagementTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No route paths were added or changed.
- At this stage, `POST /purchase-orders/{purchaseOrder}/confirm` accepted draft, approved, or sent POs; the 2026-07-18 detail workflow QA entry supersedes this behavior and restricts confirmation to sent POs.
- Existing `GET /purchase-orders/{purchaseOrder}/receive` now redirects to the PO detail with a form error when the PO is not confirmed or partially received.
- Existing `POST /purchase-orders/{purchaseOrder}/receive` now redirects back with form errors for receiving business-rule mismatches.

### Database Changes

- No schema changes.
- The receive form can create/reactivate the standard `stock_locations` seed rows when they are missing.

### Permission Changes

- No permission changes.
- Existing admin/staff receive authorization remains enforced; only receivable PO statuses can open the receive worksheet.

### Known Limitations

- Receiving still requires accepted quantity to match usable stock allocations before inventory is updated.
- `pending_approval` POs still require admin approval and cannot be directly confirmed through the confirm action.
- Custom stock location management UI is not implemented.

### Next Steps

- Recheck `/purchase-orders/{purchaseOrder}/receive` in the browser after entering mismatched allocation quantities to confirm the inline error is clear for staff.

## 2026-06-30 - Goods Receiving Page UI Redesign

### Summary

Redesigned the purchase order goods receiving form so `/purchase-orders/{purchaseOrder}/receive` reads as a compact receiving worksheet with a PO/supplier header, item metrics, grouped receiving quantity fields, and clear location allocation cards.

### Files Changed

- `resources/views/purchase-orders/receive.blade.php`
- `public/css/tinghao.css`
- Documentation files in `docs/`

### Routes Added Or Changed

- No route paths were added or changed.
- Existing `GET /purchase-orders/{purchaseOrder}/receive` UI was redesigned.

### Database Changes

- No database changes.

### Permission Changes

- No permission changes.

### Known Limitations

- Visual verification was limited to build and Blade compilation in this thread because browser control was not exposed.

### Next Steps

- Recheck `/purchase-orders/{purchaseOrder}/receive` in the browser at desktop and mobile widths.

## 2026-06-30 - Goods Receiving, Stock Allocation, And Supplier Returns

### Summary

Replaced blind PO receiving with a detailed goods receiving workflow. Receiving now records received, accepted, damaged, returned, shortage, quality status, notes, and stock allocation by location. Only accepted quantity increases ingredient inventory and creates stock-in movements. Damaged/returned quantities create supplier return records, and PO detail/dashboard surfaces shortage, damaged stock, and receiving discrepancy alerts.

### Files Changed

- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Http/Controllers/SupplierReturnController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Models/PurchaseOrder.php`
- `app/Models/PurchaseOrderItem.php`
- `app/Models/Ingredient.php`
- `app/Models/Supplier.php`
- `app/Models/StockLocation.php`
- `app/Models/StockAllocation.php`
- `app/Models/SupplierReturn.php`
- `database/migrations/2026_06_30_000001_create_stock_locations_table.php`
- `database/migrations/2026_06_30_000002_create_stock_allocations_table.php`
- `database/migrations/2026_06_30_000003_add_receiving_breakdown_to_purchase_order_items_table.php`
- `database/migrations/2026_06_30_000004_create_supplier_returns_table.php`
- `database/seeders/DatabaseSeeder.php`
- `routes/web.php`
- `resources/views/purchase-orders/show.blade.php`
- `resources/views/purchase-orders/receive.blade.php`
- `resources/views/supplier-returns/index.blade.php`
- `resources/views/supplier-returns/show.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- `public/css/tinghao.css`
- `tests/Feature/PurchaseOrderManagementTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added `GET /purchase-orders/{purchaseOrder}/receive` (`purchase-orders.receive-form`) for admin and assigned staff.
- Changed `POST /purchase-orders/{purchaseOrder}/receive` (`purchase-orders.receive`) to accept detailed receiving breakdowns and allow admin plus assigned staff.
- Added `GET /supplier-returns` (`supplier-returns.index`) for admin and staff.
- Added `GET /supplier-returns/{supplierReturn}` (`supplier-returns.show`) for admin and staff.
- Added `PATCH /supplier-returns/{supplierReturn}` (`supplier-returns.update`) for admin only.

### Database Changes

- Added `stock_locations` with seeded Store Room, Production Area, Front Counter, and Quarantine / Damaged locations.
- Added `stock_allocations` linked to ingredients, stock locations, purchase orders, purchase order items, and users.
- Added purchase order item receiving fields: `accepted_quantity`, `damaged_quantity`, `returned_quantity`, `shortage_quantity`, and `receiving_notes`.
- Added `supplier_returns` for damaged/returned supplier stock.

### Permission Changes

- Admin can receive goods, close POs, view/manage supplier returns, and update supplier return status.
- Assigned staff can receive goods and record allocations, damage, returns, and shortage.
- Staff cannot close POs or mark supplier returns resolved/rejected.

### Known Limitations

- Supplier return workflow records status only; it does not yet send supplier emails or generate return PDFs.
- Stock allocation totals are audit records for received PO quantities; a full per-location inventory balance report is not yet implemented.
- Staff receive access is limited to purchase orders where they are the requester.

### Next Steps

- Add supplier return notification/email workflow after approval rules are confirmed.
- Add GRN/return PDF export if required.
- Build location balance reporting from `stock_allocations` if operations need per-location stock totals.

## 2026-06-29 - Agent Console Workflow Stepper Readability Fix

### Summary

Fixed cramped workflow step cards on `/agent` by scoping the right-side Agent Workflow stepper to a single readable column inside the narrow panel.

### Files Changed

- `public/css/tinghao.css`
- Documentation files in `docs/`

### Routes Added Or Changed

- No route paths were added or changed.

### Database Changes

- No database changes.

### Permission Changes

- No permission changes.

### Known Limitations

- The Agent Console uses the existing two-column page structure; only the workflow card layout was adjusted.

### Next Steps

- Recheck `/agent` after future responsive CSS changes.

## 2026-06-29 - TingHao Agent Autopilot Command Center UI

### Summary

Upgraded the Agent Console and Agent Run detail presentation so TingHao Agent reads as a Track 4 Autopilot Command Center instead of a plain log page. Agent runs now foreground mission summary, next best action, dynamic workflow stepper, business impact, safety guardrails, and grouped safe Reasoning Activity.

### Files Changed

- `app/Http/Controllers/AgentController.php`
- `resources/views/agent/index.blade.php`
- `resources/views/agent/show.blade.php`
- `resources/views/components/agent/reasoning-activity.blade.php`
- `resources/views/purchase-orders/show.blade.php`
- `resources/views/supplier-email-drafts/show.blade.php`
- `public/css/tinghao.css`
- `tests/Feature/AgentConsoleTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No route paths were added or changed.
- Existing `/agent`, `/agent/runs/{agentRun}`, purchase order detail, and supplier email draft detail pages have improved presentation.

### Database Changes

- No database schema changes.

### Permission Changes

- No permission changes.
- Staff still cannot approve POs, approve supplier email drafts, or mark emails sent.

### Known Limitations

- Reasoning Activity remains safe structured summaries only and does not expose raw chain-of-thought.
- Supplier email mark-sent remains demo-safe and does not send real email.

### Next Steps

- Use the upgraded command-center flow for the Devpost demo recording.

## 2026-06-29 - TingHao Agent Phase 5 Demo Polish And Devpost Readiness

### Summary

Polished TingHao Agent for hackathon demo and Devpost readiness. Added a judge-friendly `/demo` guide, safe `/agent/proof` endpoint, richer `/health` metadata, visible eight-step Autopilot demo steppers, dashboard links/counters for agent approvals, Alibaba Cloud proof documentation, Devpost submission draft, refreshed architecture/Qwen/demo docs, README cleanup, and an MIT license.

### Files Changed

- `routes/web.php`
- `app/Http/Controllers/DashboardController.php`
- `database/seeders/DatabaseSeeder.php`
- `public/css/tinghao.css`
- `resources/views/agent/index.blade.php`
- `resources/views/agent/show.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/demo.blade.php`
- `tests/Feature/DemoReadinessTest.php`
- `LICENSE`
- `readme.md`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added `GET /demo`.
- Added `GET /agent/proof`.
- Updated `GET /health` JSON to include `mock_mode`.
- Existing `/agent` and `/agent/runs/{agentRun}` views now show clearer demo/proof links and eight-step workflow status.

### Database Changes

- No schema changes.
- Demo seed data remains update-or-create safe.
- Sugar demo notes now include the Malay `gula` alias for the Supplier Ali prompt.

### Permission Changes

- No permission changes.
- `/demo`, `/health`, and `/agent/proof` expose only safe public demo/proof metadata.
- Critical PO, supplier email, expiry, stock removal, and receiving actions remain admin guarded.

### Known Limitations

- Supplier email delivery remains demo-safe and does not send real email.
- Alibaba Cloud ECS deployment proof is documented for recording; this local task did not deploy an ECS instance.
- Mock mode remains recommended for stable no-key demos.

### Next Steps

- Record Devpost demo video using `/demo`, `/health`, and `/agent/proof`.
- If deploying to ECS, configure server-side Qwen and database environment variables without exposing secrets.

## 2026-06-29 - Pagination Icon Sizing Fix

### Summary

Fixed oversized previous/next pagination chevrons on `/inventory` and other paginated pages by adding scoped CSS for Laravel paginator SVG icons inside `.pagination-wrap`.

### Files Changed

- `public/css/tinghao.css`
- Documentation files in `docs/`

### Routes Added Or Changed

- No route paths were added or changed.
- Existing paginated pages keep using their current routes.

### Database Changes

- No database changes.

### Permission Changes

- No permission changes.

### Known Limitations

- Pagination remains Laravel's default generated markup with project-level styling applied around it.

### Next Steps

- Visually check paginated inventory, stock history, reports, suppliers, backups, agent runs, and expiry pages after future CSS changes.

## 2026-06-29 - Dashboard Recent Movement Cache Fix

### Summary

Fixed a 500 error on `/admin/dashboard` caused by the dashboard view reading cached recent movement data as Eloquent models when the cached payload could contain scalar values. Recent dashboard movement rows are now cached as plain arrays and rendered from that stable shape.

### Files Changed

- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/ExpiryLossRecommendationController.php`
- `resources/views/dashboard.blade.php`
- `tests/Feature/DashboardTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- No route paths were added or changed.
- Existing `GET /admin/dashboard` and `GET /staff/dashboard` rendering was fixed.

### Database Changes

- No database schema changes.
- Dashboard still reads recent stock movements from the real `stock_movements`, `ingredients`, and `users` tables.

### Permission Changes

- No permission changes.

### Known Limitations

- Dashboard summary cache is still a short-lived application cache and is not a substitute for long-term reporting storage.

### Next Steps

- Keep dashboard cache invalidation aligned with future stock movement and expiry-loss workflows.

## 2026-06-29 - TingHao Agent Phase 4.5 Reasoning Activity And Guardrails

### Summary

Added structured Reasoning Activity and centralized human-in-the-loop guardrails. Agent runs now store safe explainability steps such as observe, understand, tool action, tool result, decision, risk check, human checkpoint, and final summary. The system explicitly avoids requesting or storing raw model chain-of-thought.

### Files Changed

- `app/Http/Controllers/AgentController.php`
- `app/Http/Controllers/ExpiryController.php`
- `app/Http/Controllers/ExpiryLossRecommendationController.php`
- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Http/Controllers/SupplierEmailDraftController.php`
- `app/Models/AgentReasoningStep.php`
- `app/Models/AgentRun.php`
- `app/Models/AgentToolCall.php`
- `app/Services/Agent/HumanApprovalGuardService.php`
- `app/Services/Agent/ProcurementMessageParserService.php`
- `app/Services/Agent/ReasoningActivityService.php`
- `app/Services/Agent/SupplierEmailDraftService.php`
- `app/Services/Agent/TingHaoAgentService.php`
- `app/Services/Agent/ExpiryLossPreventionService.php`
- `database/migrations/2026_06_29_000002_create_agent_reasoning_steps_table.php`
- `public/css/tinghao.css`
- `resources/views/agent/show.blade.php`
- `resources/views/components/agent/reasoning-activity.blade.php`
- `resources/views/expiry-loss-recommendations/show.blade.php`
- `resources/views/purchase-orders/show.blade.php`
- `resources/views/supplier-email-drafts/show.blade.php`
- `tests/Feature/AgentConsoleTest.php`
- `docs/reasoning-activity-and-human-loop.md`
- Documentation files in `docs/`
- `readme.md`

### Routes Added Or Changed

- No new route paths were added.
- Existing agent, purchase order, supplier email draft, expiry recommendation, and expired-stock actions now use shared guardrails and/or show Reasoning Activity where applicable.

### Database Changes

- Added `agent_reasoning_steps`.
- Added relationships from `AgentRun` and `AgentToolCall`.

### Permission Changes

- Staff still cannot approve purchase orders, approve/send supplier email drafts, complete expiry recommendations, remove expired stock, or receive PO stock.
- Admin-only critical actions now pass through `HumanApprovalGuardService`.

### Known Limitations

- Reasoning Activity stores concise summaries and evidence, not raw chain-of-thought.
- Existing non-agent manual PO email sending remains a manual/admin workflow.

### Next Steps

- Add optional filtering/search for reasoning steps if audit volume grows.

## 2026-06-29 - TingHao Agent Phase 4 Expiry Loss Prevention

### Summary

Added an Expiry Loss Prevention Agent that scans ingredients expiring within 7 days, calculates potential RM loss from real inventory quantity and cost price, asks Qwen for practical bakery usage recommendations, stores recommendations, and displays measurable RM impact on the dashboard and Agent Console.

### Files Changed

- `app/Http/Controllers/AgentController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/ExpiryLossRecommendationController.php`
- `app/Models/AgentRun.php`
- `app/Models/ExpiryLossRecommendation.php`
- `app/Models/Ingredient.php`
- `app/Services/Agent/ExpiryLossPreventionService.php`
- `database/migrations/2026_06_29_000001_create_expiry_loss_recommendations_table.php`
- `database/seeders/DatabaseSeeder.php`
- `public/css/tinghao.css`
- `resources/views/agent/expiry-loss.blade.php`
- `resources/views/agent/index.blade.php`
- `resources/views/agent/show.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/expiry-loss-recommendations/show.blade.php`
- `routes/web.php`
- `tests/Feature/AgentConsoleTest.php`
- `docs/phase-4-expiry-loss-prevention.md`
- Documentation files in `docs/`
- `readme.md`

### Routes Added Or Changed

- Added `GET /agent/expiry-loss`
- Added `POST /agent/expiry-loss/scan`
- Added `GET /expiry-loss-recommendations/{expiryLossRecommendation}`
- Added `POST /expiry-loss-recommendations/{expiryLossRecommendation}/review`
- Added `POST /expiry-loss-recommendations/{expiryLossRecommendation}/dismiss`
- Added `POST /expiry-loss-recommendations/{expiryLossRecommendation}/complete`
- Updated `/agent`, agent run detail, and dashboard pages with Expiry Loss Prevention entry points.

### Database Changes

- Added `expiry_loss_recommendations` for quantity at risk, cost price, potential RM loss, expiry date, Qwen recommendation, status, reviewer, ingredient link, and agent run link.
- Added model relationships from `Ingredient` and `AgentRun`.
- Updated seed butter data to provide a 7-day RM216 expiry loss demo.

### Permission Changes

- Admin can run scans, review, dismiss, and complete recommendations.
- Staff can view Expiry Loss Prevention pages and recommendation details.
- Staff cannot run scans or change recommendation status.

### Known Limitations

- This phase does not add full recipe management, POS integration, Excel import/export, or live demand forecasting.
- Recommendations are practical usage suggestions only and do not claim real sales projections.
- Missing cost prices prevent RM loss calculation for that ingredient.

### Next Steps

- Add optional notification rules for high RM risk thresholds.
- Add production email/WhatsApp delivery only after approval and audit rules are confirmed.

## 2026-06-28 - TingHao Agent Phase 3 Supplier Email Draft

### Summary

Added the Phase 3 supplier email draft workflow for approved agent-created purchase orders. Qwen now generates a supplier email draft from the real PO data, the draft is saved for admin review, admins can approve it, and admins can mark it sent for demo purposes without sending a real email.

### Files Changed

- `app/Http/Controllers/AgentController.php`
- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Http/Controllers/SupplierEmailDraftController.php`
- `app/Models/AgentRun.php`
- `app/Models/PurchaseOrder.php`
- `app/Models/Supplier.php`
- `app/Models/SupplierEmailDraft.php`
- `app/Services/Agent/SupplierEmailDraftService.php`
- `database/migrations/2026_06_28_000005_create_supplier_email_drafts_table.php`
- `public/css/tinghao.css`
- `resources/views/agent/show.blade.php`
- `resources/views/purchase-orders/show.blade.php`
- `resources/views/supplier-email-drafts/show.blade.php`
- `routes/web.php`
- `tests/Feature/AgentConsoleTest.php`
- `docs/phase-3-supplier-email-draft.md`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added `POST /purchase-orders/{purchaseOrder}/email-draft`
- Added `GET /supplier-email-drafts/{supplierEmailDraft}`
- Added `POST /supplier-email-drafts/{supplierEmailDraft}/approve`
- Added `POST /supplier-email-drafts/{supplierEmailDraft}/mark-sent`
- Updated purchase order and agent run detail pages to link to the latest supplier email draft.

### Database Changes

- Added `supplier_email_drafts` for generated supplier email subject/body, status, approval user, sent timestamp, and links to purchase orders, suppliers, and agent runs.

### Permission Changes

- Admin can generate, approve, and mark supplier email drafts sent.
- Staff can view only supplier email drafts attached to purchase orders they requested.
- Staff cannot generate, approve, or mark supplier email drafts sent.

### Known Limitations

- No real email is sent in this Phase 3 workflow.
- SMTP, Gmail, WhatsApp, and external messaging integrations remain intentionally out of scope.
- Draft generation falls back to deterministic local content when Qwen mock mode is enabled or Qwen returns invalid JSON.

### Next Steps

- Add production email delivery only after approval, logging, and environment rules are confirmed.
- Add optional admin edit-before-approve behavior if business users need to revise generated drafts.

## 2026-06-28 - NPM Dependency Security Updates

### Summary

Resolved the reported `npm audit` vulnerabilities by widening the direct Axios version range and running a non-force audit fix. The lockfile now resolves patched versions for Axios, form-data, concurrently/shell-quote, and Vite.

### Files Changed

- `package.json`
- `package-lock.json`
- `docs/CHANGELOG.md`
- `docs/TODO.md`

### Routes Added Or Changed

- None.

### Database Changes

- None.

### Permission Changes

- None.

### Known Limitations

- None for this dependency security update.

### Next Steps

- Continue running `npm audit` after frontend dependency changes.

## 2026-06-28 - TingHao Agent Phase 2 Autonomous Restock Engine

### Summary

Extended the Phase 1 Agent Console into a Phase 2 autonomous restock engine. The agent now parses procurement messages, checks inventory, calculates restock quantities, ranks suppliers, creates a real purchase order draft using the existing PO tables, creates an approval request, links the PO from the agent run detail page, and lets admins approve or reject the draft.

### Files Changed

- `app/Http/Controllers/AgentController.php`
- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Models/AgentRun.php`
- `app/Models/ApprovalRequest.php`
- `app/Models/PurchaseOrder.php`
- `app/Services/Agent/InventoryLookupToolService.php`
- `app/Services/Agent/PurchaseOrderDraftService.php`
- `app/Services/Agent/RestockPlanningService.php`
- `app/Services/Agent/SupplierRankingService.php`
- `app/Services/Agent/TingHaoAgentService.php`
- `database/migrations/2026_06_28_000004_add_agent_approval_fields_to_purchase_orders.php`
- `public/css/tinghao.css`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- `resources/views/agent/show.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/purchase-orders/index.blade.php`
- `resources/views/purchase-orders/show.blade.php`
- `routes/web.php`
- `tests/Feature/AgentConsoleTest.php`
- `tests/Feature/PurchaseOrderManagementTest.php`
- `docs/phase-2-autonomous-restock-engine.md`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added `POST /purchase-orders/{purchaseOrder}/approve`
- Added `POST /purchase-orders/{purchaseOrder}/reject`
- Existing `/agent/run` now creates a pending-approval PO draft when restock planning succeeds.
- Existing `/purchase-orders` now limits staff to POs they requested.

### Database Changes

- Extended existing `purchase_orders` with `agent_run_id`, `requested_by`, `approved_by`, and `agent_reasoning`.
- Added `approval_requests` for human review of agent-created PO drafts.
- Did not create duplicate `purchase_orders` or `purchase_order_items` tables because the project already had real PO tables.

### Permission Changes

- Staff can run the agent and request PO drafts.
- Staff can view only their own requested purchase orders.
- Admin can view all purchase orders and approve or reject pending PO drafts.

### Known Limitations

- Phase 2 creates PO drafts only; it does not send supplier emails automatically.
- Supplier ranking is deterministic and based on linked supplier, contact info, and parsed supplier hint.
- Approved agent POs do not yet move into supplier communication automatically.

### Next Steps

- Phase 3 should add approved supplier communication tools.
- Add richer supplier performance signals once delivery history exists.
- Add optional admin edit-before-approval behavior if required.

## 2026-06-28 - TingHao Agent Foundation MVP

### Summary

Built the first Track 4 Autopilot Agent foundation. Added server-side Qwen configuration/client with mock mode, procurement message parsing, inventory and supplier lookup tools, persisted agent run/tool-call audit logs, an authenticated Blade Agent Console, dashboard/sidebar entry points, richer `/health` JSON, and demo seed coverage for sugar/milk supplier examples.

### Files Changed

- `config/qwen.php`
- `app/Http/Controllers/AgentController.php`
- `app/Models/AgentRun.php`
- `app/Models/AgentToolCall.php`
- `app/Services/Qwen/QwenClient.php`
- `app/Services/Agent/TingHaoAgentService.php`
- `app/Services/Agent/ProcurementMessageParserService.php`
- `app/Services/Agent/InventoryLookupToolService.php`
- `app/Services/Agent/SupplierLookupToolService.php`
- `database/migrations/2026_06_28_000002_create_agent_runs_table.php`
- `database/migrations/2026_06_28_000003_create_agent_tool_calls_table.php`
- `database/seeders/DatabaseSeeder.php`
- `routes/web.php`
- `.env.example`
- `resources/views/agent/index.blade.php`
- `resources/views/agent/show.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/components/dashboard/sidebar.blade.php`
- `public/css/tinghao.css`
- `tests/Feature/AgentConsoleTest.php`
- `docs/qwen-usage.md`
- `docs/architecture.md`
- `docs/demo-script.md`
- Documentation files in `docs/`
- `readme.md`

### Routes Added Or Changed

- Added `GET /agent`
- Added `POST /agent/run`
- Added `GET /agent/runs/{agentRun}`
- Changed `GET /health` from plain `ok` to safe JSON with service, track, Qwen configured flag, and database status.

### Database Changes

- Added `agent_runs` for user input, parsed intent, final summary, Qwen mock flag, and status.
- Added `agent_tool_calls` for tool name, input payload, output payload, and status.
- Seeder now includes `Supplier Ali` and `Whole Milk Carton` demo data for agent prompts.

### Permission Changes

- Admin and staff can access `/agent` and run the agent.
- Admin can view all agent runs.
- Staff can view only their own agent runs.

### Known Limitations

- This MVP recommends and logs procurement intent only; it does not create purchase orders.
- Real Qwen mode requires environment variables and network access.
- Mock mode uses deterministic fallback parsing and is intentionally lightweight.
- WhatsApp, email sending, human approval checkpoints, and PO approval automation remain future phases.

### Next Steps

- Add human approval checkpoints before creating purchase orders.
- Convert accepted recommendations into draft purchase orders.
- Add supplier communication tools after approval.
- Expand parser examples and automated tests around mixed-language procurement messages.

## 2026-06-28 - Performance Audit And Practical Optimizations

### Summary

Audited the main Laravel routes, controllers, Blade views, database queries, assets, and Render deployment config for demo and production performance. Optimized dashboard queries, paginated previously unbounded report/alert/expiry pages, reduced relationship payloads, added local-only performance logging, added supporting indexes, and made health/static asset handling lighter.

### Files Changed

- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/IngredientController.php`
- `app/Http/Controllers/StockMovementController.php`
- `app/Http/Controllers/LowStockController.php`
- `app/Http/Controllers/ExpiryController.php`
- `app/Http/Controllers/SupplierController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Middleware/LogLocalPerformance.php`
- `app/Models/Ingredient.php`
- `bootstrap/app.php`
- `database/migrations/2026_06_28_000001_add_performance_indexes.php`
- `docker/nginx.conf.template`
- `render.yaml`
- `routes/web.php`
- `resources/views/auth/login.blade.php`
- `resources/views/home.blade.php`
- `resources/views/alerts/low-stock.blade.php`
- `resources/views/expiry/index.blade.php`
- `resources/views/reports/inventory.blade.php`
- `resources/views/reports/stock.blade.php`
- `resources/views/reports/low-stock.blade.php`
- `resources/views/reports/expiry.blade.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added `GET /health` for a lightweight health response.
- Render health check now uses lightweight `/health`.

### Database Changes

- Added indexes for low-stock, category/name, supplier/name, stock movement date/type/date, restock status/date, and purchase order status/date query paths.

### Permission Changes

- None.

### Known Limitations

- Dashboard summary is cached for 60 seconds, so counts can be briefly stale after inventory or stock changes.
- Public/login pages still use remote image URLs, but loading hints and smaller login image parameters were added. Replacing remote images with local optimized assets remains future work.
- Report tables are paginated for performance; users must move through pages for full data review.

### Next Steps

- Capture before/after timings from `storage/logs/laravel.log` while `APP_ENV=local`.
- Replace remote public/login images with optimized local images.
- Consider a dedicated Redis/cache service if dashboard traffic grows.

## 2026-06-27 - Real Purchase Order Workflow Audit Fixes

### Summary

Audited the main system flows and converted the visible purchase order path from demo-first navigation to the real database-backed purchase order workflow. Added real supplier confirmation, stock receiving, received quantity tracking, stock movement creation, and PO close actions.

### Files Changed

- `routes/web.php`
- `app/Http/Controllers/PurchaseOrderController.php`
- `app/Http/Controllers/LowStockController.php`
- `app/Models/PurchaseOrder.php`
- `app/Models/PurchaseOrderItem.php`
- `app/Models/RestockRequest.php`
- `database/migrations/2026_06_27_000001_add_receiving_fields_to_purchase_orders.php`
- `resources/views/components/dashboard/sidebar.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/alerts/low-stock.blade.php`
- `resources/views/purchase-orders/index.blade.php`
- `resources/views/purchase-orders/show.blade.php`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- `tests/Feature/PurchaseOrderManagementTest.php`
- `tests/Feature/LowStockWorkflowTest.php`
- Documentation files in `docs/`

### Routes Added Or Changed

- Added `POST /purchase-orders/{purchaseOrder}/confirm`
- Added `POST /purchase-orders/{purchaseOrder}/receive`
- Added `POST /purchase-orders/{purchaseOrder}/close`
- Updated dashboard and low-stock PO shortcuts to use real `purchase-orders.*` routes.

### Database Changes

- Added `confirmed_at`, `received_at`, and `closed_at` to `purchase_orders`.
- Added `received_quantity` and `quality_status` to `purchase_order_items`.

### Permission Changes

- Admin can confirm, receive, and close real purchase orders.
- Staff can view real purchase orders but cannot create, edit, email, confirm, receive, close, or delete them.
- Admin can now mark restock requests as `rejected`.

### Known Limitations

- Smart Stock Memory Planner remains a calendar-based planning preview.
- Help Center remains static guidance content.
- Full GRN document generation is still future work.
- Real supplier SMTP depends on environment mail configuration; the PO workflow still records the email step when the send action is clicked.

### Next Steps

- Build a full GRN workflow if the business requires separate goods-received documents.
- Replace the Smart Stock Memory Planner preview with live movement-history recommendations.
- Add supplier performance tracking.

## 2026-06-27 - Documentation Workflow

### Summary

Added a required documentation workflow for Codex/AI coding agents so every system change must include matching documentation updates before the task is complete.

### Files Changed

- `AGENTS.md`
- `docs/CHANGELOG.md`
- `docs/current-function-inventory.md`
- `docs/backend-api.md`
- `docs/prd.md`
- `docs/database.md`
- `docs/ui-guide.md`
- `docs/TODO.md`

### Routes Added Or Changed

- None.

### Database Changes

- None.

### Permission Changes

- None.

### Known Limitations

- This workflow relies on coding agents following `AGENTS.md`; it is not enforced by automated CI yet.
- Documentation was aligned at a high level, but deeper per-controller examples can still be expanded later.

### Next Steps

- Add a CI or review checklist that fails or flags code changes without documentation updates.
- Continue updating all relevant docs whenever routes, UI, database schema, permissions, reports, localization, email workflows, dashboards, or demo modules change.
# 2026-07-18 - Header language switcher spacing

- Feature/module updated: Shared application header and dashboard language selector.
- Summary of change: Reserved header space for the language button so it does not overlap the Admin/profile action on desktop or mobile screens.
- Files changed: `public/css/tinghao.css` and required documentation files.
- Routes added/changed: None.
- Database changes: None.
- Permission changes: None.
- Known limitations: The language selector remains a shared fixed control outside the dashboard header.
- Next steps: Verify the header at desktop and narrow mobile widths.

## 2026-07-18 - Purchase Order Goods Receiving Usability

### Feature/Module Updated

- Purchase Orders / Goods Receiving worksheet.

### Summary Of Change

- Clean receiving rows now default to `Accepted / Good`, while quantity edits update the displayed quality status for damage, returns, shortages, or partial acceptance.
- Clarified that received quantity equals accepted plus damaged plus shortage, with returned quantity tracked separately for supplier returns.
- Kept Quarantine / Damaged allocation neutral until damaged or returned quantity is greater than zero.
- Added a responsive bottom Back / Record Receiving action area and disabled both submit buttons after a valid submission starts.

### Files Changed

- `resources/views/purchase-orders/receive.blade.php`
- `public/css/tinghao.css`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- `tests/Feature/PurchaseOrderManagementTest.php`
- Required documentation files in `docs/`.

### Routes Added Or Changed

- None. Verified `GET /purchase-orders/{purchaseOrder}/receive` and `POST /purchase-orders/{purchaseOrder}/receive`.

### Database Changes

- None.

### Permission Changes

- None. Admin and assigned staff retain the existing receiving access; status and ownership guards remain unchanged.

### Known Limitations

- The worksheet does not total quantities across mixed units because kilograms, packs, cartons, and other units are not safely additive.
- Live browser screenshot QA is still required at the final desktop and mobile demo widths.

### Next Steps

- Browser-test clean, damaged, returned, shortage, and multi-item receiving scenarios before the final demo.

## 2026-07-18 - Purchase Order Detail Timeline Consistency

### Feature/Module Updated

- Purchase Orders / detail workflow timeline and approval metadata.

### Summary Of Change

- Manual Email Sent is completed only when `purchase_orders.sent_at` exists; an equivalent sent supplier draft is shown as `Marked Sent`.
- Received POs now show Received as completed and Closed as the current next step.
- Manual POs show Approved by as `Not applicable`; Agent and Stock Prediction approval wording remains unchanged.

### Files Changed

- `resources/views/purchase-orders/show.blade.php`
- `resources/lang/en/messages.php`
- `resources/lang/zh_CN/messages.php`
- `tests/Feature/PurchaseOrderManagementTest.php`
- Required documentation files in `docs/`.

### Routes Added Or Changed

- None. Verified `GET /purchase-orders/{purchaseOrder}`.

### Database Changes

- None.

### Permission Changes

- None. Existing admin/staff action visibility and controller authorization remain unchanged.

### Known Limitations

- Legacy rows with later workflow statuses but no email timestamp intentionally show the email step as incomplete, exposing the missing audit evidence.
- Final browser screenshot QA remains pending.

### Next Steps

- Browser-check manual draft, sent, confirmed, received, closed, and legacy missing-timestamp records before the demo.

## 2026-07-18 - Agent Workflow Visualizer Simplification

### Feature/Module Updated

- Agent Audit Console / Autopilot Workflow Visualizer.

### Summary Of Change

- Replaced duplicated Template and Live node-detail lists with one interactive Selected Step Details panel.
- Kept Template View as the generic procurement architecture and hid selected-run metadata until Live Run View is active.
- Added run-type mapping so expiry-loss missions render a seven-step expiry workflow without procurement-only PO, supplier, or email nodes.
- Mapped node colors and linked records from existing tool calls, recommendations, approvals, purchase orders, and supplier email drafts.

### Files Changed

- `app/Http/Controllers/AgentController.php`
- `resources/views/agent/index.blade.php`
- `public/css/tinghao.css`
- `tests/Feature/AgentConsoleTest.php`
- Required documentation files in `docs/`.

### Routes Added Or Changed

- None. Verified `GET /agent` and its existing optional `?run={id}` selection.

### Database Changes

- None.

### Permission Changes

- None. Staff remain scoped to their own AgentRun records; admin can inspect all visible runs.

### Known Limitations

- Runs created before detailed audit logging may show `Not recorded` for individual tools or linked records.
- Final live-browser screenshot QA remains pending at laptop and mobile widths.

### Next Steps

- Browser-check Template/Live switching, node selection, procurement runs, expiry runs, failed runs, and narrow layouts before the demo.

## 2026-07-18 - Cross-Device Responsive Demo Hardening

### Feature/Module Updated

- Shared authenticated UI, Dashboard, Stock Planner, Purchase Orders, Goods Receiving, Supplier Email Draft, and Agent Audit.

### Summary Of Change

- Added one scoped responsive layer for 1366px+ desktop, 1024-1365px laptop, 768-1023px tablet, and 375-767px mobile layouts.
- Extended the Dashboard sidebar drawer through tablet widths, prevented language/header overlap, and made metric, autopilot, management, analytics, and recent-activity grids wrap predictably.
- Added a keyboard-focusable, internally scrollable Stock Planner calendar surface and responsive card/detail/advice layouts.
- Kept purchase-order and audit tables inside responsive containers, stacked PO detail actions, and made receiving fields and allocations two columns on tablet and one column on mobile.
- Added long-text wrapping, compact mobile spacing, touch targets, and responsive Supplier Email Draft and Agent workflow panels without changing business behavior.

### Files Changed

- `public/css/tinghao.css`
- `resources/views/stock-planner/index.blade.php`
- `tests/Feature/StockPlannerPredictionTest.php`
- `docs/CHANGELOG.md`
- `docs/current-function-inventory.md`
- `docs/backend-api.md`
- `docs/prd.md`
- `docs/database.md`
- `docs/ui-guide.md`
- `docs/TODO.md`

### Routes Added Or Changed

- None. Checked existing Dashboard, Stock Planner, Purchase Order, receiving, Supplier Email Draft, and Agent Audit routes.

### Database Changes

- None.

### Permission Changes

- None. Existing staff/admin route middleware and Blade action visibility are unchanged.

### Known Limitations

- Browser screenshot comparison still depends on the final demo browser, font loading, and representative seeded records.
- Wide data tables may use contained horizontal scrolling where their complete column set cannot fit; the page itself remains width-constrained.

### Next Steps

- Run the manual four-width browser checklist and capture final demo screenshots with representative admin and staff records.
- 2026-07-21 — AI provider architecture preparation
  - Feature/module updated: AI provider abstraction.
  - Summary: Added a provider contract, Qwen adapter, and environment-configured OpenAI GPT-5.6 client. Existing Qwen and procurement workflows are unchanged.
  - Files changed: `app/Contracts/AI/StructuredDecisionProvider.php`, `app/Services/AI/QwenStructuredProvider.php`, `app/Services/OpenAI/OpenAIClient.php`, `config/ai.php`, `.env.example`.
  - Routes added/changed: None.
  - Database changes: None.
  - Permission changes: None.
  - Known limitations: The new provider is not wired into an existing workflow yet; OpenAI mock mode is enabled by default.
  - Next steps: Inject the contract into a future procurement scenario service when that feature is implemented.
- 2026-07-21 — AI provider selection binding
  - Feature/module updated: AI provider foundation.
  - Summary: Bound the structured decision provider contract to Qwen by default or OpenAI when `AI_PROVIDER=openai`, with focused resolution coverage.
  - Files changed: `app/Providers/AppServiceProvider.php`, `tests/Unit/AIProviderResolutionTest.php`.
  - Routes added/changed: None.
  - Database changes: None.
  - Permission changes: None.
  - Known limitations: The provider contract is not connected to existing procurement workflows.
  - Next steps: Use the contract from the future Procurement Scenario Comparison service.
- 2026-07-21 — GPT procurement review service
  - Feature/module updated: Procurement AI review foundation.
  - Summary: Added a provider-backed, validation-only procurement review service using existing supplier comparisons. Human approval is always required.
  - Files changed: `app/Services/Procurement/GptProcurementReviewService.php`, `tests/Unit/GptProcurementReviewServiceTest.php`.
  - Routes added/changed: None.
  - Database changes: None.
  - Permission changes: None.
  - Known limitations: The service does not create POs, alter stock, send email, or expose a route.
  - Next steps: Integrate only through a future reviewed procurement scenario workflow.
- 2026-07-21 — GPT-5.6 Review in Stock Planner
  - Feature/module updated: Stock Planner ingredient prediction detail.
  - Summary: Added an inline GPT-5.6 Review action and result panel. Reviews remain advisory and require human approval.
  - Files changed: `app/Http/Controllers/StockPlannerController.php`, `routes/web.php`, `resources/views/stock-planner/show.blade.php`, `tests/Feature/GptProcurementReviewTest.php`.
  - Routes added/changed: Added `POST /stock-planner/ingredient/{ingredient}/gpt-review` for admin/staff users.
  - Database changes: None.
  - Permission changes: Existing `admin,staff` role middleware applies; no new permission model was added.
  - Known limitations: The review does not create POs, modify stock, or send email.
  - Next steps: Keep any future procurement execution behind existing approval workflows.
