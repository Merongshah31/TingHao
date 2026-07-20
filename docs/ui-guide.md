# Ting Hao UI Guide

Last updated: 2026-07-19

This guide documents current UI pages, main actions, role visibility, and design notes.

2026-07-19 verification note: PO detail coverage now checks both mutually exclusive delivery states: demo mode shows Mark Email as Sent and hides Resend; Resend test mode shows Send Test Email via Resend and hides Mark Email as Sent.

2026-07-20 Resend note: test-mode UI may identify the configured test recipient, while supplier email remains the intended business recipient and is never used as the provider destination in test mode.

2026-07-19 audit note: the Agent workflow view can show a rejected premature stop and Laravel's deterministic fallback while the mission continues through its required checkpoint.

## Design Direction

- Use the existing Ting Hao bakery/inventory theme.
- Keep operational screens clear, compact, and easy to scan.
- Use role-aware buttons so staff do not see actions they cannot perform.
- Keep admin-only system controls visually separated from daily staff workflows.
- Maintain English and Simplified Chinese labels when localization text changes.
- Keep Laravel pagination controls compact; paginator SVG arrows are scoped under `.pagination-wrap`.
- Phase 5 demo pages should be judge-friendly, concise, and safe for public viewing.
- Stock Planner now provides Prediction View and Calendar View from the same FastAPI-backed prediction results, with Qwen business explanations only on explicit detail/explain flows and restock PO drafts only after a user clicks the plan-restock action.
- Dashboard sidebar keeps the existing dark-green theme, uses a stable desktop width, hides horizontal overflow, scrolls vertically only inside the menu area, and collapses into a drawer on smaller screens.

## Pages

### Home

- Route: `GET /`
- Purpose: Public Ting Hao landing page.
- Main actions: Open login page; view static business sections.
- Visibility: Public.
- UI notes: Search, product cards, map, and footer links are currently placeholder/static. Public imagery still uses remote URLs but includes loading/decoding hints where practical.

### Login

- Route: `GET /login`
- Purpose: Admin/staff authentication.
- Main actions: Submit email/password; remember me.
- Visibility: Guest users.
- UI notes: Forgot password is visual only.

### Admin Dashboard

- Route: `GET /admin/dashboard`
- Purpose: Admin system overview.
- Main actions: Review Today's Autopilot Actions, open inventory, alerts, suppliers, reports, settings, backups, purchase orders, supplier returns, TingHao Agent audit console, stock memory demo, and help center shortcuts where available.
- Visibility: Admin only.
- UI notes: Dashboard analytics are CSS/Blade based, not chart-library based. Today's Autopilot Actions show low-stock restock planning, PO approval, supplier email draft review, and expiry-loss risk cards. PO drafts created from Stock Prediction should read as an ingredient restock plan waiting approval. Recent movement rows render from cached scalar data and show ingredient, actor, timestamp, and signed quantity. Purchase order shortcuts show open supplier returns and receiving discrepancy counters.
- Stock Prediction Signals note: Dashboard shows up to three cached important prediction cards and links each card to Stock Planner detail. Add-stock cards never show `Suggested: 0.00`; non-purchase cards use business-friendly wording. Dashboard does not call FastAPI directly.
- Autopilot UI note: Each Today's Autopilot Actions card should show a visible status badge and one primary Review, Approve, or View link.
- Email-draft UI note: An approved PO without a supplier email draft uses the `Needs email draft` badge and retains the Review action.
- Management Center icon note: Purchase Orders and TingHao Agent cards use icons from the existing loaded Lucide set so their icon tiles are never blank.
- Stock Planner card note: Suggested Quantity appears only for a valid positive add-stock suggestion. Do Not Buy, Buy Less, Monitor, Use Before Expiry, and expired-stock review cards show Purchase Advice instead.
- Calendar note: Each day displays no more than two highest-priority badges. Date Advice lists four secondary signals at most and summarizes any remainder with one compact line.
- Restock control note: Show `Plan Restock with TingHao Agent` only when action, quantity, and supplier checks pass. Otherwise show `Refresh prediction` or `Assign supplier before restock.` guidance.
- Detail safety note: Expired items show `This item is past expiry. Review or remove expired stock before restocking.` and an Expiry link. Pending quantity shows `A pending purchase order already exists for this item.` plus the existing PO/list link.
- Detail action order: expired-stock review first, then pending-PO review, then a valid restock action, followed by non-purchase guidance.
- Audit disclosure note: Use `Technical Audit Details` for the collapsed JSON/metadata section and state that it is for judges/developers only with no API keys or raw chain-of-thought.
- Expiry wording note: Expired stock uses `Review Expired Stock`; only usable near-expiry stock uses `Use Before Expiry`.
- Demo display note: Stock Planner may apply exact English aliases to known demo names and units while preserving stored database values.
- Permission UI note: Admin can see global PO approval and supplier email draft action cards. Staff should only see PO/email action cards tied to purchase orders they requested.
- Permission UI note: Staff pending PO Autopilot cards should say Review, not Approve. The Human Review / Agent Approvals dashboard shortcut is admin-only.
- Sidebar note: Daily users should see "Autopilot Actions" as a clickable icon navigation item. Primary groups are Workspace, Alerts, Procurement, and Analytics; `/agent` appears as "Agent Audit" under Audit & Demo. Sidebar labels stay on one line, active links use a rounded highlight, and hover/focus-visible states must remain keyboard accessible.

### Staff Dashboard

- Route: `GET /staff/dashboard`
- Purpose: Staff operational overview.
- Main actions: Review Today's Autopilot Actions, open daily inventory, stock, alerts, supplier, report, supplier returns, stock memory demo, TingHao Agent audit console, and help center shortcuts where allowed.
- Visibility: Staff only.
- UI notes: Staff should not see admin-only settings or destructive controls. Recent movement rows use the same cached scalar display shape as the admin dashboard.

### Agent Audit Console

- Routes: `GET /agent`, `GET /agent/runs/{agentRun}`
- Purpose: Audit, proof, judging, and developer debugging view for TingHao Agent.
- Main actions: Review Qwen/proof status cards, inspect Autopilot Workflow Visualizer, select a live run, review recent runs, open linked PO approval from run detail when a draft is created, open linked supplier email draft after generation, open Expiry Loss Prevention, open Demo Guide, open Proof JSON.
- Visibility: Admin and staff can use the console. Admin sees all runs; staff sees only their own runs.
- UI notes: The page shows Qwen mode as "Live Qwen mode" or "Mock demo mode", model name, server-side configured state, parsed intent, extracted ingredients, inventory matches, supplier matches, restock plan, linked PO draft, and the sequence `Message Parsed -> Inventory Checked -> Restock Planned -> Supplier Ranked -> PO Drafted -> Waiting for Admin Approval`. Summary cards should not self-loop; the email draft card points daily users back to Dashboard Autopilot Actions.
- Removed UI note: `/agent` no longer shows the old Smart Procurement Inbox, message textarea, sample prompt buttons, Run Agent Audit button, or old Visible Activity vertical workflow cards.
- Workflow Visualizer UI note: `/agent` shows one Autopilot Workflow map directly after summary/status cards and one Selected Step Details panel below it. Template View displays the static procurement architecture. Live Run View displays procurement nodes or an expiry-specific seven-node flow based on the latest/selected AgentRun. Clicking a node updates the single business-friendly detail panel.
- Workflow Visualizer status colors: completed is green, pending is yellow, failed/blocked is red, and not-started is gray. Admin Approval must clearly show "Human-in-the-loop checkpoint."
- Permission UI note: Agent Audit summary counts are global for admin and scoped for staff, matching their permitted PO/email draft ownership.
- Route UI note: Agent mission detail should generate supplier email drafts through `purchase-orders.generate-email-draft`, not the legacy compatibility alias.
- Autopilot Command Center UI notes: Agent run detail should foreground mission summary, next best action, workflow stepper, business impact, and safety guardrails before technical tool logs.
- Agent Audit workflow panel note: The compact right-side workflow stepper should render as a readable single-column list inside the narrow panel.
- Reasoning Activity UI notes: Agent-linked workflows show grouped safe summaries for Observe, Analyze, Plan, Tool Actions, Decision, Human Checkpoint, and Execution / Outcome. The UI does not show raw chain-of-thought.

### Demo Guide

- Route: `GET /demo`
- Purpose: Judge-friendly guide for testing TingHao Agent.
- Main actions: Open login, Agent Audit, purchase orders, expiry loss page, health endpoint, and proof endpoint.
- Visibility: Public.
- UI notes: Shows demo accounts, copy-ready prompts, three-minute demo path, safe proof links, and recent demo activity without exposing secrets.

### Expiry Loss Prevention

- Routes: `GET /agent/expiry-loss`, `GET /expiry-loss-recommendations/{expiryLossRecommendation}`
- Purpose: Show measurable RM impact from ingredients expiring within 7 days and Qwen-backed usage recommendations.
- Main actions: Admin runs expiry scan, reviews recommendation, dismisses recommendation, or marks recommendation completed.
- Visibility: Admin and staff can view. Admin controls scans and status changes.
- UI notes: Dashboard card shows potential RM at risk, ingredient count, open recommendations, and highest-risk ingredient. Recommendation detail shows quantity, cost price, potential loss, expiry date, days until expiry, status, Qwen recommendation, and audit link.

### Inventory List And Forms

- Routes: `GET /inventory`, `GET /inventory/create`, `GET /inventory/{ingredient}`, `GET /inventory/{ingredient}/edit`
- Purpose: Ingredient inventory management.
- Main actions: Search/filter, add ingredient, view detail, edit/delete as admin, record stock movement.
- Visibility: Admin and staff can view/add; admin can edit/delete.
- UI notes: Low-stock and expiry states should remain visible in lists and details. Inventory pagination should stay compact with normal-sized previous/next chevrons.

### Stock Movement

- Routes: `GET /stock/history`, `GET /inventory/{ingredient}/stock/{type}`
- Purpose: Record and review stock in/out.
- Main actions: Filter history; submit stock in or stock out.
- Visibility: Admin and staff.
- UI notes: Forms should clearly show ingredient and movement type.

### Low Stock Alerts

- Route: `GET /alerts/low-stock`
- Purpose: Review low-stock ingredients and restock requests.
- Main actions: Request restock, ask TingHao Agent to plan restock, update restock status as admin.
- Visibility: Admin and staff can view/request; admin can update status.
- UI notes: Keep status labels clear for pending and completed work. The TingHao Agent panel explains that the agent prepares a PO draft only and admin approval is still required.

### Inventory Detail Agent Recommendation

- Route: `GET /inventory/{ingredient}`
- Purpose: Show item-specific agent recommendation when an ingredient is low stock or expiring soon.
- Main actions: Ask Agent to Plan Restock for low-stock ingredients, or open expiry tracking for expiring items.
- Visibility: Admin and staff can view. Agent restock planning still creates pending admin approval.
- UI notes: Recommendation copy should be plain business language, not technical logs.

### Purchase Orders

- Routes: `GET /purchase-orders`, `GET /purchase-orders/create`, `GET /purchase-orders/{purchaseOrder}`, `GET /purchase-orders/{purchaseOrder}/edit`
- Purpose: Manage real supplier purchase orders.
- Main actions: Create PO, edit PO, delete PO, approve/reject agent-created PO drafts, generate supplier email draft, approve draft, mark draft sent for demo, mark eligible PO confirmed, open goods receiving, record accepted/damaged/returned/shortage quantities, allocate received stock to locations, close PO.
- Visibility: Admin can view all and manage approval/actions. Staff can view purchase orders they requested and receive assigned goods. Staff cannot close POs.
- UI notes: Agent-created drafts show recommendation summary and approval status first. Stock Prediction-created drafts show a "Created from Stock Prediction" badge plus prediction source, Qwen explanation source when available, current/minimum stock, predicted action, stockout estimate, suggested quantity, risk, confidence, reason codes, and "Admin approval required." Technical reasoning activity is hidden under Advanced Details. Approved POs show the supplier email section; if no draft exists, admins see "Generate Supplier Email Draft", and if a draft exists they see "Review Supplier Email Draft." Mark-sent is labelled as demo-safe and does not send real email. Supplier and ingredient line items should stay easy to review before receiving stock. PO list and detail pages show status/source badges. Goods receiving links are active only for confirmed or partially received POs; other statuses show “Receive available after PO confirmed.” PO detail shows received, accepted, damaged, shortage, quality status, receiving summary, allocation summary, and warning badges for shortage or supplier return required.
- Agent approval UI note: Agent-created PO details should show suggested item quantities, supplier, approval state, and why the order was suggested before Advanced Details.

### Goods Receiving

- Route: `GET /purchase-orders/{purchaseOrder}/receive`
- Purpose: Record delivery receiving details before inventory changes.
- Main actions: Enter received quantity, accepted quantity, damaged quantity, returned quantity, shortage quantity, quality status, notes, and allocation quantities for Store Room, Production Area, Front Counter, and Quarantine / Damaged.
- Visibility: Admin and assigned staff, only after the PO is confirmed or partially received.
- UI notes: The form uses a worksheet layout with a PO/supplier header, item metric cards, grouped receiving fields, and compact allocation cards. The page restores the standard allocation cards if location seed data is missing. Normal accepted stock defaults to Store Room. Usable allocation must match accepted quantity, and mismatches return to the same worksheet with an error message and old input. Damaged stock can be quarantined or returned. The old blind receive-full shortcut is removed from PO detail.

### Supplier Returns

- Routes: `GET /supplier-returns`, `GET /supplier-returns/{supplierReturn}`
- Purpose: View damaged/returned supplier stock records created during PO receiving.
- Main actions: View return number, supplier, ingredient, PO number, damaged quantity, returned quantity, reason, status, creator, and created date. Admin can update status/reason.
- Visibility: Admin and staff can view. Admin can update supplier return status.
- UI notes: Status labels should remain professional and operational. Staff should not see update controls.

### Supplier Email Draft

- Route: `GET /supplier-email-drafts/{supplierEmailDraft}`
- Purpose: Review generated supplier email subject/body before demo mark-sent.
- Main actions: Admin approves a draft; admin regenerates a draft while status is draft; admin marks an approved draft sent for demo.
- Visibility: Admin can review and act on all drafts. Staff can view drafts attached to purchase orders they requested.
- UI notes: The page shows status, supplier, email/contact, linked PO, source as Qwen Cloud, Qwen model, approver, approval timestamp, sent timestamp, real-email safety note, draft subject/body, and PO item context. Technical reasoning activity is hidden under Advanced Details.
- Safety wording: The draft page should clearly state that no real email is sent automatically and admin controls final action.

### Purchase Order Demo

- Routes: `GET /purchase-order-demo`, `GET /purchase-order-demo/create`, `GET /purchase-order-demo/{po}`
- Purpose: Presentation-ready PO workflow demo.
- Main actions: Create demo PO, preview/send demo email step, confirm supplier, receive stock, close PO.
- Visibility: Admin and staff can view; admin creates/sends/confirms/closes; staff can receive stock.
- UI notes: Legacy presentation route remains available, but primary navigation now points to the real purchase order workflow.

### Expiry

- Route: `GET /expiry`
- Purpose: Review expiring-soon and expired ingredients.
- Main actions: Review Expiry Loss Prevention card and remove expired stock as admin.
- Visibility: Admin and staff can view; admin removes expired stock.
- UI notes: Separate expired and expiring-soon items visually. The page shows an Expiry Loss Prevention card with ingredient risk, days until expiry, potential RM loss, and recommended action.

### Suppliers

- Routes: `GET /suppliers`, `GET /suppliers/create`, `GET /suppliers/{supplier}`, `GET /suppliers/{supplier}/edit`
- Purpose: Supplier management.
- Main actions: Search, add, view, edit.
- Visibility: Admin and staff can view; admin can add/edit.
- UI notes: Show linked ingredients where useful.

### Reports

- Routes: `GET /reports`, `GET /reports/inventory`, `GET /reports/stock`, `GET /reports/low-stock`, `GET /reports/expiry`, `GET /reports/generated-summary`, `GET /reports/generated-summary/pdf`
- Purpose: Inventory, stock, low-stock, expiry, and generated summary reporting.
- Main actions: View reports; admin opens/downloads generated summary PDF.
- Visibility: Admin and staff can view standard reports; generated summary and PDF are admin only.
- UI notes: Report tables should remain printable and easy to scan. Large report tables are paginated for performance.

### Stock Planner Legacy Redirects

- Routes: `GET /stock-memory-demo`, `GET /calendar-demo`
- Purpose: Backward-compatible redirects to `/stock-planner?view=calendar`.
- Main actions: Redirect only.
- Visibility: Admin and staff.
- UI notes: These routes should not appear as separate business modules or top-level actions.

### Stock Planner

- Routes: `GET /stock-planner?view=cards`, `GET /stock-planner?view=calendar`, `GET /stock-planner/ingredient/{ingredient}/prediction`, `POST /stock-planner/ingredient/{ingredient}/refresh-prediction`, `POST /stock-planner/ingredient/{ingredient}/explain`, `POST /stock-planner/ingredient/{ingredient}/plan-restock`
- Purpose: Show FastAPI-backed stock action predictions from compact Laravel inventory summaries.
- Main actions: Review prediction cards, refresh one ingredient prediction, open prediction detail, explain with Qwen, plan restock with TingHao Agent when the prediction recommends `add_stock_now` or `add_stock_soon`, open expiry page when the prediction recommends use before expiry.
- Visibility: Admin and staff.
- UI notes: Prediction View cards show ingredient name, supplier name, current stock, minimum stock, predicted action, estimated stockout days, suggested quantity, risk, confidence, reason badges, refresh, and detail actions. Normal refreshes reuse cached predictions while the cache is valid; the Refresh Prediction button forces only the selected ingredient. Calendar View shows date-based stock planning signals from the same cached/generated prediction results, max two labels per day plus `+N more`, and a right-side date advice panel. Raw API response is hidden under Advanced Details on the detail page. Prediction service outage state is shown as friendly copy, not an exception page.
- Qwen UI notes: Detail page shows Qwen Explanation with professional English-only title, summary, business reason, warning, recommended next step, user-friendly action, and confidence label. The explanation action is labelled Generate/Regenerate English Explanation and replaces old cached explanation text for the current prediction snapshot. Cached explanations show "AI explanation generated from latest prediction." Advanced Details show Qwen model, mock mode, latency, token usage, and cache hit/miss only.
- Restock planning UI notes: The plan-restock button appears only for add-stock predictions. `do_not_buy`, `buy_less`, and `monitor` show advisory text; `use_before_expiry` links to the expiry workflow. After successful planning, the user is redirected to the PO review page with "Restock plan created. Purchase order draft is waiting for admin approval."
- Safety wording: FastAPI is the prediction engine, Qwen is the explanation layer, and Laravel controls workflow. Purchase orders are not approved, sent, or received automatically from a prediction or explanation.

### Help Center

- Route: `GET /help-center`
- Purpose: In-app support and workflow guidance.
- Main actions: Read guidance and FAQs.
- Visibility: Admin and staff.
- UI notes: Keep wording aligned with localization files.

### System Settings And Backups

- Routes: `GET /system/settings`, `GET /system/backups`
- Purpose: Admin system configuration and backup snapshot audit.
- Main actions: Update settings, create snapshot, clean up snapshots, delete snapshot.
- Visibility: Admin only.
- UI notes: Backup screens must explain that snapshots are not full database backups.
# Maintenance note (2026-07-18)

The language selector now occupies a reserved area in the dashboard header and no longer overlaps the Admin/profile action. On narrow screens it remains compact and retains keyboard-accessible links.

## Purchase Orders Index (2026-07-18)

- Route: `GET /purchase-orders`.
- The action column uses stacked compact controls and keeps passive next-step guidance visually separate from active buttons.
- Status guidance: pending approval = `Waiting admin approval`; approved = `Prepare supplier email draft`; sent = `Confirm before receiving`; confirmed = `Receive Goods`; partially received = `Continue Receiving`; received = `Ready to close` for admin or `Received` for staff; closed = `Completed`; rejected/cancelled = `No further action`.
- `Created from Stock Prediction` remains the exact source badge for prediction-generated POs.
- At narrower widths, requested-by, expected-delivery, and sent-at columns are deprioritized; mobile rows use labeled cards and do not require horizontal scrolling.
- Staff see View and valid receiving links only. Admin workflow controls remain available only when the PO status supports them.

## Create Purchase Order Suggestions (2026-07-18)

- Route: `GET /purchase-orders/create`; suggestion data: `GET /purchase-orders/suggestions`.
- Visibility: Admin only.
- Supplier + order date suggests an editable expected delivery date and displays: `Estimated from supplier lead time and previous purchase orders.`
- Ingredient selection fills unit, positive quantity, and unit price. Supplier changes refresh supplier-specific price suggestions; quantity and price input continue updating line total locally.
- The Items helper reads: `Select ingredients, quantities, units, and prices. Suggestions use Stock Planner and previous purchase orders.`
- Suggestions are visually secondary and advisory. No automatic Save, PO creation, approval, FastAPI call, Qwen call, or email occurs.

## Purchase Order Detail Workflow (2026-07-18)

- Route: `GET /purchase-orders/{purchaseOrder}`.
- The page shows one workflow timeline. Completed steps are green, the current step is yellow, rejected/blocked is red, and future steps are gray.
- Manual labels: Draft → Email Sent → Supplier Confirmed → Received → Closed.
- Agent labels: PO Drafted → Admin Approved → Email Drafted → Email Approved → Marked Sent. Rejected by Admin replaces the normal future flow only when rejected.
- One Next step panel contains the valid action. No generic `Receive available after PO confirmed` control appears on terminal or pre-confirmation states.
- Email wording uses Generate Supplier Email Draft, Review Supplier Email Draft, and Mark Email as Sent, with the note that no real email is sent automatically and admin controls the final action.
- Staff never see admin-only approve/reject, email mutation, supplier confirmation, or close controls.

## Goods Receiving Worksheet Polish (2026-07-18)

- Route: `GET /purchase-orders/{purchaseOrder}/receive` for confirmed or partially received POs.
- Clean rows display `Accepted / Good`. Quantity edits update the visible quality status to returned, damaged, shortage, or partially accepted as appropriate.
- Helper copy reads: `Received quantity must equal accepted + damaged + shortage. Returned quantity is recorded for supplier return tracking.`
- Quarantine / Damaged cards are neutral at zero and use the warning treatment only when damaged or returned quantity is greater than zero.
- A bottom Back / Record Receiving action area supports long multi-item forms; the existing top Record action remains available.
- Submit controls are disabled once a valid submission starts. Accepted stock is still written only after server validation succeeds.

## Purchase Order Detail Timeline Consistency (2026-07-18)

- Route: `GET /purchase-orders/{purchaseOrder}`.
- Manual steps are Draft → Email Sent/Marked Sent → Supplier Confirmed → Received → Closed.
- Completed steps are green, the current next action is amber, and future or unsupported steps are gray.
- Email Sent is green only when `sent_at` exists. A sent supplier draft without that timestamp uses the business-safe label `Marked Sent`.
- When status is `received`, Received is green and Closed is amber because Close Purchase Order is the next action.
- Manual Approved by displays `Not applicable`; Agent/Stock Prediction approval wording and action visibility are unchanged.

## Agent Workflow Visualizer Layout (2026-07-18)

- Template View: generic procurement architecture only; selected run status, owner, and date cards stay hidden.
- Live Run View: selected run summary plus a procurement or expiry-loss map chosen from run type.
- Expiry map: Trigger → Inventory Scan → Expiry Risk Calculated → RM Loss Calculated → Qwen Recommendation → Admin Review → Audit Logged.
- One Selected Step Details panel shows status, tool label, business detail, linked record, and the human-in-the-loop badge where applicable.
- Green means completed, amber means pending/current, red means failed/blocked, and gray means not started. Missing live evidence is described as `Not recorded`.
- The map uses five columns on wide screens, two on medium screens, and one on mobile with no horizontal overflow.

## Responsive Layout Guide (2026-07-18)

- Breakpoints: desktop is 1366px+, laptop is 1024-1365px, tablet is 768-1023px, and mobile is 375-767px. Shared content uses fluid widths and `min-width: 0` so children cannot widen the page.
- Sidebar/Dashboard: the sidebar remains 252px and fixed through laptop widths, then becomes the existing keyboard-dismissable drawer at 1023px and below. The language switcher moves below the mobile workspace so it does not overlap header controls.
- Dashboard: metrics progress from six to three, two, then one column; autopilot and Management Center cards progress from four to two to one; analytics and recent movement/low-stock panels stack on mobile.
- Stock Planner: prediction cards are three/two/one columns. At tablet width the Date Advice panel sits below Calendar View. At mobile width the seven-day calendar has a focusable internal horizontal scroll region; the document does not scroll sideways.
- Purchase Orders: the index keeps its responsive column priorities/mobile row treatment inside the table card. Detail summaries become one column on mobile, workflow steps wrap to two then one column, item tables scroll inside their cards, and action groups stack.
- Goods Receiving: receiving and allocation fields are two columns on tablet and one on mobile. The bottom action bar remains reachable and becomes sticky near the bottom on narrow screens.
- Supplier Email Draft: subject and email body wrap within their card; admin actions stack full-width on mobile. Agent Audit nodes use two columns on compact mobile/tablet layouts and one on the narrowest screens, with Selected Step Details below the map.
- Main mobile actions use at least 44px height. Existing green theme, typography, status colors, route links, and role visibility are preserved.

## Phase 1 Autopilot UI (2026-07-18)

- Stock Prediction detail shows an evidence table for eligible suppliers only when a buy action can be planned. Rank, price, lead time, receiving exceptions, contact availability, and `Insufficient history` stay business-readable in a contained responsive table.
- Agent-created PO detail preserves FastAPI prediction context and now shows the supplier comparison used for the draft. Admin can open Edit and choose another supplier before approval.
- Supplier Email Draft detail lets admin edit subject/body. Saving approved content visibly returns it to Draft so approval must happen again.
- With `REAL_EMAIL_ENABLED=false`, approved drafts show Mark Email as Sent and explicitly state no delivery occurs. With valid Resend configuration, the action changes to Send Test Email via Resend in test mode or Send to Supplier via Resend in production mode. When enabled but incomplete, the page shows the server configuration message and no unsafe fallback action.
- In `RESEND_TEST_MODE=true`, the draft page shows the Resend Test Mode badge and the configured recipient. In production mode, it shows the linked supplier name and a masked supplier email.
- Resend send forms confirm "This will send a real email to {masked recipient}" before submission.
- Delivery mode, status, provider label, and attempt time are visible; credentials and raw provider payloads are not.
- Provider message IDs appear only in collapsed Technical Audit Details after a send attempt.
- The Phase 1 capability map appears on `/agent` and `/demo` in sequential Observe through Audit order. Status colors are green completed, amber waiting, red failed, and gray available/not configured, with direct evidence links.
- Staff do not see email edit/approve/send or PO approval/confirm/close controls. Admin-only route middleware remains the security boundary behind UI visibility.
- `/demo` uses the real Phase 1 path: scheduled observation, Stock Planner review, approval-gated PO, explicit Qwen email draft, admin delivery action, supplier confirmation, receiving, close, and audit. It no longer directs judges to the retired message-entry panel.

## Supplier Email Draft Compatibility (2026-07-19)

- The existing supplier email draft page remains editable and its admin-only actions retain the same wording and visibility on deployments awaiting the delivery-audit migration.
- The UI does not expose missing technical fields or claim delivery evidence when the database cannot yet store it. Applying the migration restores normal delivery status/provider/attempt display.

## Restock Decision Loop Audit (2026-07-19)

- Agent Run detail shows a Bounded Qwen Decision Loop table only for runs that contain loop evidence; other mission layouts remain unchanged.
- Each row displays iteration, observation, selected action, Laravel tool result, safe reason summary/confidence, decision source, and stop reason.
- The table uses the existing responsive table container and appears before Reasoning Activity. It explicitly states the four-iteration limit and that raw chain-of-thought is not requested or stored.
- `/agent` Live Run recognizes `qwen_select_next_action` as Qwen reasoning evidence. Existing proof links, visualizer, recent missions, technical tool payloads, and role scoping remain unchanged.
# Agent Audit Visualizer UI (2026-07-19)

- `/agent` uses a run picker followed by Run Summary, Workflow Strip, Audit Timeline, Human Checkpoint, Final Outcome, and collapsed Technical Audit Details.
- The old Template View, Live Run View, repeated node detail content, and node-selection JavaScript are no longer rendered.
- Workflow colors: green completed, amber pending/current, red failed/blocked, gray skipped/not used.
- Timeline rows show timestamp, safe tool/result/decision summaries, confidence, action, approval state, and one short reason.
- Recent mission `Inspect run` links reload `/agent` with the selected run and jump to the visualizer. The full technical audit remains linked from the collapsed section.
- At 920px the summary/workflow grids use four columns; at 720px audit content reduces to two columns and governance cards stack; at 440px all audit grids stack to one column.

## Agent Audit Milestone UI (2026-07-19)

- `/agent` starts with Selected Live Mission after summary statistics. `How TingHao Autopilot Works` is a collapsed disclosure below it.
- Seven clickable milestone cards replace the long micro-event timeline and generic workflow strip.
- One Selected Step Details panel updates with populated fields only; empty decision, confidence, and approval rows are omitted.
- Actor badges: Qwen Decision uses violet, FastAPI Prediction uses blue, Laravel Tool uses green, Human Approval uses amber, and System Audit uses gray.
- Milestones use four columns at 920px, two at 720px, and one at 440px. Technical Audit Details remains collapsed.
