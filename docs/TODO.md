# Ting Hao TODO

Last updated: 2026-07-19

Track pending work and future improvements here. Move items into `docs/CHANGELOG.md`, `docs/current-function-inventory.md`, and related docs when implemented.

## Completed Resend Test Isolation (2026-07-19)

- [x] Make demo-mode email tests explicitly set `autopilot.real_email_enabled=false`.
- [x] Cover both demo Mark Sent and Resend test-mode PO detail actions without using local `.env` values.
- [x] Keep the high-confidence autopilot draft scenario covered with duplicate and safety guardrails intact.

## Completed Bounded Restock Stop Safety (2026-07-19)

- [x] Reject premature Qwen stops before required workflow checks.
- [x] Use state-based allowed actions and deterministic Laravel fallback.
- [x] Keep duplicate-PO, expiry-review, supplier comparison, draft, and approval tests covered.

## Documentation Workflow

- Add CI or review checklist support to flag code changes without documentation updates.
- Expand controller-level examples in `docs/backend-api.md` as workflows mature.
- Keep `docs/database.md` synchronized with every migration.
- Keep `docs/ui-guide.md` synchronized with every page, dashboard shortcut, wording, and localization change.

## Performance

- Replace remote Unsplash/public-page images with optimized local images in `public/images`.
- Capture before/after dashboard timings from local performance logs.
- Keep dashboard cache data shapes covered by regression tests when adding new cached widgets.

## Completed Dashboard QA (2026-07-18)

- [x] Remove meaningless zero suggested quantities from Dashboard Stock Prediction Signals.
- [x] Clarify the approved-PO email-draft badge.
- [x] Restore Purchase Orders and TingHao Agent Management Center icons.
- [ ] Run the final dashboard screenshot check with representative admin and staff data.

## Completed Stock Planner QA (2026-07-18)

- [x] Add positive fallback quantities for zero/invalid add-stock predictions.
- [x] Hide meaningless zero quantities for non-purchase actions.
- [x] Separate expired-stock review from usable near-expiry advice.
- [x] Limit Calendar View day badges and secondary advice links.
- [x] Gate restock buttons by action, quantity, and supplier availability.
- [x] Add display-only English aliases for identified demo names and units.
- [ ] Capture final browser screenshots after refreshing stale prediction caches.

## Completed Stock Planner Detail Safety (2026-07-18)

- [x] Block expired items from active restock planning in UI and service.
- [x] Block pending-PO items from duplicate restock planning and link to the existing PO.
- [x] Reconcile cached predictions with full detail input before rendering.
- [x] Rename the collapsed technical section and add judge/developer safety wording.
- [ ] Capture final detail screenshots for expired, pending-PO, and valid-restock states.
- Consider Redis or another external cache store if the app outgrows the database cache store.
- Add export-specific report flows if users need full data downloads beyond paginated HTML.
- Recheck shared pagination styling after any future CSS reset or framework change.

## Demo And Devpost

- Record the 3-minute Devpost video using `docs/demo-script.md`.
- Capture Alibaba Cloud ECS proof following `docs/alibaba-cloud-deployment-proof.md`.
- Add screenshots or GIFs to Devpost after deployment URL is stable.
- Capture screenshots of the Autopilot Command Center mission detail page for the Devpost gallery.
- Recheck the simplified `/agent` workflow visualizer at common laptop/mobile widths before recording the demo, including Template/Live switching, the single detail panel, procurement and expiry runs, linked records, and selected-run loading.
- Browser-check simplified `/agent` to confirm the old Smart Procurement Inbox/message form and old Visible Activity workflow cards are gone, and the visualizer remains the main focus before recent missions.
- Run one live browser walkthrough of the final hackathon path: Stock Planner cards/calendar, plan restock, admin PO approval/reject, Qwen supplier email draft, Dashboard Autopilot Actions, Agent Audit, proof endpoints, staff visibility, `/health`, and `/demo`.
- During the live walkthrough, confirm staff dashboards show Review wording for pending PO actions and never show the admin-only Human Review / Agent Approvals shortcut.
- Browser-check the dashboard sidebar at desktop, tablet, and mobile widths to confirm no horizontal scrollbar, vertical menu scrolling, active highlight, keyboard focus states, and drawer open/close behavior.

## Dependency Security

- Run `npm audit` after future frontend dependency updates.
- Review dependency ranges before using `npm audit fix --force`.

## Purchase Order

### Completed Purchase Order Detail Workflow QA (2026-07-18)

- [x] Replace conflicting manual/approval timelines with one origin-aware timeline.
- [x] Hide rejected from normal future steps and make terminal states action-free.
- [x] Add status-specific Next step wording and valid action controls.
- [x] Clarify Agent approval and demo-safe supplier email wording.
- [x] Restrict supplier confirmation to sent POs and retain confirmed-only receiving.
- [x] Verify staff/admin action visibility and no Qwen request on page load.
- [ ] Capture final manual, pending Agent, approved Agent, rejected, and closed PO screenshots.

### Completed Create PO Suggestions (2026-07-18)

- [x] Suggest delivery dates from supplier PO history with a two-day fallback.
- [x] Suggest positive quantities from cached Stock Planner output or stock-level fallback.
- [x] Suggest unit prices from supplier/ingredient history or ingredient cost.
- [x] Keep all fields editable and preserve explicit Save as the only creation action.
- [x] Protect the suggestion endpoint as admin-only and test that no prediction HTTP call occurs.
- [ ] Browser-check suggestion changes, manual overrides, validation reloads, and mobile table behavior with production-like data.

### Completed Purchase Orders Index QA (2026-07-18)

- [x] Replace generic receiving hints with status-specific next steps.
- [x] Keep terminal/rejected POs free of misleading receiving actions.
- [x] Stack and wrap index actions without horizontal table overflow.
- [x] Keep Stock Prediction source badges compact and readable.
- [x] Verify staff/admin action visibility with feature tests.
- [ ] Capture final admin and staff screenshots at laptop and mobile widths with long production-like names.

- Connect PO email workflow to production SMTP configuration and delivery monitoring only after approval and audit rules are confirmed.
- Add optional admin edit-before-approve behavior for generated supplier email drafts if required.
- Add browser QA for supplier email draft generate, existing-draft reuse, regenerate, approve, and mark-sent states.
- Add optional confirmation dialog before supplier email draft regeneration.
- Build full GRN workflow.
- Add supplier return email/notification workflow after approval and audit rules are confirmed.
- Add supplier return PDF/export if required.
- Add per-location stock balance reporting from `stock_allocations` if operations need location totals.
- Recheck the redesigned goods receiving worksheet in a live browser at common desktop and mobile widths, including disabled receive states, restored stock allocation cards, and corrected allocation validation error state.
- Add supplier performance tracking.
- Add PDF/export behavior for purchase orders if required.

## Stock Planning

- Add full Stock Planner production persistence and calendar history logic.
- Connect stock memory recommendations to real inventory usage patterns.
- Add browser QA for the Stock Planner plan-restock flow, including success redirect to PO review, duplicate pending PO message, missing supplier message, and non-buy advisory states.
- In local browser QA, confirm Stock Planner first load logs cache misses, second load logs cache hits, and Refresh Prediction logs a single selected-ingredient refresh.
- Add automated edge-case tests for `prediction-service` recommendations, including missing usage, expiry soon, high stock, pending PO quantity, weekend risk, and festival risk.
- Add local service authentication or network restrictions before any production deployment.
- Decide where Laravel should persist prediction snapshots if business users need audit history.
- Add optional `stock_predictions` database table if cached recommendations need long-term audit history.
- Decide whether Qwen stock prediction explanations need long-term database audit history beyond cache.
- In live Qwen mode, browser-check several Stock Planner Qwen explanations for professional English-only wording and no invented sales/customer/demand claims.
- Add browser screenshot QA for `/stock-planner?view=cards`, `/stock-planner?view=calendar`, date advice panel, Qwen Explanation, Advanced Details, and Dashboard prediction signals.
- Add month navigation for Stock Planner Calendar View if operators need to inspect future prediction dates beyond the current month.
- Add optional translation/localization keys for Stock Planner and Qwen Explanation labels after wording stabilizes.
- Add optional persisted stock prediction restock context table if audit needs outgrow `agent_runs.parsed_intent`.
- Keep Qwen limited to explaining prediction results in simple business language, not performing raw forecasting calculations.

## TingHao Agent

- Recheck embedded autopilot cards across Dashboard, Low Stock, Inventory detail, Purchase Order detail, Supplier Email Draft, and Expiry pages in a live browser, including Dashboard status badges and PO item/quantity summary wrapping.
- Recheck sidebar wording after the Agent Audit / Autopilot Actions navigation change.
- In live Qwen mode, verify Qwen usage metadata includes HTTP status, latency, and token counts when Qwen returns usage data.
- Add production supplier communication delivery only after Phase 3 draft approval is reviewed.
- Add optional expiry-loss notification thresholds for high RM risk items.
- Consider richer expiry recommendation templates only if recipe/product data is later introduced.
- Add optional filtering/search for Reasoning Activity timelines if audit volume grows.
- Add WhatsApp/email integrations only after secure approval rules are defined.
- Expand tests for Qwen JSON recovery and mixed-language parsing examples.
- Add richer tool authorization if future tools mutate inventory or purchase orders.
- Add optional admin edit-before-approval flow if operationally required.

## Reports

- Add admin Excel export for inventory reports.
- Add admin Excel export for stock movement reports.
- Add admin Excel export for low-stock reports.
- Add admin Excel upload/import where requirements confirm it is safe.

## Users And Permissions

- Add staff account management UI.
- Add profile editing.
- Consider dedicated permission tables if role rules become more complex.

## POS And API

- Add Laravel Sanctum or another approved token system.
- Build `/api/v1` JSON endpoints.
- Build POS sale sync endpoint.
- Add product and recipe mapping.
- Add automatic stock deduction from POS sales.

## Public Website

- Replace placeholder public search with real search.
- Replace placeholder map/contact/footer content with production details.
- Replace external demo images with approved local assets.
# Completed maintenance (2026-07-18)

- [x] Prevent the shared language selector from overlapping the dashboard Admin/profile action.
- [ ] Perform visual browser checks at the final supported desktop and mobile breakpoints.
- [x] Default clean PO receiving rows to Accepted / Good and keep empty quarantine cards neutral.
- [x] Add a bottom Record Receiving action area for long purchase orders.
- [ ] Browser-test damaged, returned, shortage, and multi-item receiving at final demo viewport sizes.
- [x] Align manual PO timeline completion with email evidence and received/close states.
- [x] Show manual PO Approved by metadata as Not applicable.
- [ ] Browser-check legacy POs with later statuses but missing `sent_at` so incomplete audit evidence is clear during the demo.
- [x] Replace duplicated `/agent` Template/Live node detail lists with one Selected Step Details panel.
- [x] Use an expiry-specific Live Run workflow for `expiry_loss_prevention` missions.
- [ ] Capture final `/agent` screenshots for procurement, expiry, failed, and no-run states at laptop and mobile widths.
- [x] Add shared desktop/laptop/tablet/mobile responsive rules for the final authenticated demo workflow.
- [x] Contain Stock Planner calendar scrolling and wide data tables so they do not widen the document.
- [x] Align Dashboard drawer, card grids, PO actions, receiving forms, supplier drafts, and Agent visualizer with the supported breakpoints.
- [ ] Complete final visual browser screenshots at 1366px, 1024px, 768px, and 375px using representative admin and staff data.

## Phase 1 Autopilot Completion (2026-07-18)

- [x] Add deduplicated scheduled low-stock/expiry observation with cached FastAPI predictions and no Qwen calculation calls.
- [x] Add evidence-based supplier comparison with explicit insufficient-history labels.
- [x] Add safe quantity fallback, pending-PO guard, and optional high-confidence automatic `pending_approval` drafts.
- [x] Keep automatic PO drafting disabled by default and preserve mandatory admin approval.
- [x] Add admin supplier email content editing with reapproval after edits.
- [x] Add optional explicit Resend delivery with safe success/failure audit metadata.
- [x] Audit supplier confirmation, goods receiving discrepancies, and final PO closure.
- [x] Add real-status Phase 1 capability maps to `/agent` and `/demo`.
- [ ] Run `php artisan migrate` in each deployed environment.
- [ ] Apply `2026_07_18_000001_add_delivery_audit_to_supplier_email_drafts.php` to the active Supabase deployment, then verify supplier-email draft edit, demo mark-sent, and Resend audit evidence.
- [ ] Configure a production scheduler/cron to execute `php artisan schedule:run` every minute.
- [ ] Run `2026_07_19_000001_add_resend_fields_to_supplier_email_drafts_table.php` in each deployed environment.
- [ ] Configure and verify `RESEND_API_KEY`, `RESEND_TEST_RECIPIENT`, `RESEND_FROM_ADDRESS`, and `RESEND_FROM_NAME` before enabling `REAL_EMAIL_ENABLED=true`.
- [ ] Send one controlled Resend test message to `bakerytinghao@outlook.com` and verify the inbox plus accepted/failed audit evidence.
- [ ] Add Resend webhook handling if delivery, bounce, or failure status should update after initial acceptance.
- [ ] Capture final admin/staff demo screenshots with at least two suppliers containing representative PO history.

## Bounded Restock Decision Loop (2026-07-19)

- [x] Add a four-iteration Qwen next-action selector to Stock Planner restock missions.
- [x] Validate every action in Laravel and use deterministic fallback for invalid or malformed model output.
- [x] Stop duplicate-PO and expired-stock branches without creating a draft.
- [x] Stop eligible draft creation at pending admin approval.
- [x] Show compact decision evidence in Agent Audit without raw chain-of-thought or secrets.
- [ ] Record the three live-Qwen demo branches and capture the corresponding Agent Audit screens.
# Agent Audit Visualizer Follow-up (2026-07-19)

- [x] Consolidate `/agent` into one selected-run audit visualizer with safe timeline and human checkpoint evidence.
- [x] Mark missing or irrelevant workflow evidence as skipped/not used instead of completed.
- [ ] Validate representative live-Qwen restock and expiry runs before the final demo; older records may lack confidence or reviewer timestamps.

## Agent Audit Milestone Follow-up (2026-07-19)

- [x] Collapse the static capability map and reduce the visible live audit to seven milestones.
- [x] Show one selected detail panel with source badges and relevant fields only.
- [ ] Capture final desktop/mobile screenshots using a live FastAPI restock run to demonstrate the FastAPI Prediction badge.
