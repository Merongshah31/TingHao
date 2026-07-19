# Phase 2 Autonomous Restock Engine

Last updated: 2026-06-28

Phase 2 extends TingHao Agent from message parsing into a human-approved restock workflow.

## Flow

```text
Procurement message
  -> parse procurement intent
  -> lookup inventory
  -> plan restock quantity
  -> rank suppliers
  -> create purchase order draft
  -> create approval request
  -> wait for admin approval
```

## Important Design Choice

The project already had real `purchase_orders` and `purchase_order_items` tables. Phase 2 does not duplicate them. It extends the existing purchase order workflow with agent metadata, approval status, and human review.

## Human-In-The-Loop Checkpoint

Agent-created POs use `pending_approval`. Admin users can approve or reject them. Staff can run the agent and view their own requested PO drafts, but cannot approve or reject.

## Tool Calls Logged

- `parse_procurement_message`
- `lookup_inventory`
- `plan_restock_quantity`
- `rank_suppliers`
- `create_purchase_order_draft`
- `create_approval_request`

## Current Limits

- Email sending is not part of Phase 2 agent automation.
- Approved agent POs do not automatically contact suppliers.
- Receiving stock remains part of the existing real PO workflow.
- Supplier ranking is deterministic and uses current supplier metadata.
