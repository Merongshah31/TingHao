# TingHao Agent Phase 3 Supplier Email Draft

Date: 2026-06-28

## Summary

Phase 3 adds a demo-safe supplier communication step after an agent-created purchase order is approved. Qwen generates a supplier email draft from the real purchase order, the draft is saved for admin review, the admin can approve it, and the admin can mark it sent without sending any real email.

## Workflow

```text
Agent message
  -> pending-approval purchase order
  -> admin approves purchase order
  -> Qwen generates supplier email draft
  -> admin approves draft
  -> admin marks sent for demo
  -> purchase order status becomes sent
```

## Routes

| Method | URI | Route Name | Access | Purpose |
| --- | --- | --- | --- | --- |
| POST | `/purchase-orders/{purchaseOrder}/email-draft` | `purchase-orders.email-draft` | Admin | Generate supplier email draft for an approved PO |
| GET | `/supplier-email-drafts/{supplierEmailDraft}` | `supplier-email-drafts.show` | Admin, Staff owner | View supplier email draft |
| POST | `/supplier-email-drafts/{supplierEmailDraft}/approve` | `supplier-email-drafts.approve` | Admin | Approve draft content |
| POST | `/supplier-email-drafts/{supplierEmailDraft}/mark-sent` | `supplier-email-drafts.mark-sent` | Admin | Mark draft sent for demo without SMTP |

## Database

New table:

- `supplier_email_drafts`

Important fields:

- `purchase_order_id`
- `supplier_id`
- `agent_run_id`
- `subject`
- `body`
- `status`: `draft`, `approved`, or `sent`
- `approved_by`
- `sent_at`

Relationships:

- A purchase order has many supplier email drafts.
- A supplier has many supplier email drafts.
- An agent run has many supplier email drafts.
- A supplier email draft belongs to the user who approved it.

## Tool Calls

The agent audit timeline records these tool names when a draft is linked to an agent run:

- `generate_supplier_email_draft`
- `approve_supplier_email_draft`
- `mark_supplier_email_sent`

## Safety

- No real email is sent.
- No SMTP, Gmail, WhatsApp, or external messaging integration is used.
- The mark-sent action records the demo state, updates `purchase_orders.status` to `sent`, and stores `sent_at`/`email_to`.
- Staff cannot generate, approve, or mark supplier email drafts sent.

## Verification

- Feature coverage exists in `tests/Feature/AgentConsoleTest.php`.
- The test confirms admin-only generation/approval/mark-sent behavior and asserts `Mail::assertNothingSent()`.
