# TingHao Agent Demo Script

Last updated: 2026-06-29

Goal: show the Qwen Cloud Hackathon Track 4 Autopilot Agent in under 3 minutes.

## Setup

1. Open `/demo`.
2. Keep `QWEN_MOCK_MODE=true` for reliable no-key demo mode, or configure Qwen env variables for live Qwen.
3. Demo accounts:
   - Admin: `admin@tinghao.com` / `password`
   - Staff: `staff@tinghao.com` / `password`

## Three-Minute Flow

### 0:00 - 0:20 Problem

Small bakery teams receive messy stock messages, low-stock warnings, supplier context, and expiry risk. TingHao Agent turns that into structured procurement actions with human approval.

### 0:20 - 0:45 Staff Pastes Messy Restock Message

1. Login as staff.
2. Open `/agent`.
3. Paste: `gula dah abis boss, nak order 50kg dari Supplier Ali tak?`
4. Click `Run TingHao Agent`.

### 0:45 - 1:15 Reasoning And Tool Calls

Show:

- Parsed message.
- Matched sugar inventory.
- Supplier Ali match.
- Restock quantity.
- Reasoning Activity.
- Tool call timeline.

### 1:15 - 1:45 PO Draft And Human Approval

Show the linked purchase order draft and status `pending approval`. Explain that the agent cannot approve or send anything by itself.

### 1:45 - 2:10 Admin Approval And Email Draft

1. Login as admin.
2. Open the linked PO.
3. Approve the PO.
4. Generate supplier email draft.

### 2:10 - 2:30 Approve And Mark Sent

Open the supplier email draft, approve it, and mark it sent. Explain this is demo-safe and does not send real email.

### 2:30 - 2:45 Expiry Loss RM Impact

Open `/agent/expiry-loss`, run the scan, and show RM at risk for near-expiry ingredients.

### 2:45 - 3:00 Alibaba/Qwen Proof

Open:

- `/health`
- `/agent/proof`

Closing line: TingHao Agent is a Laravel full-stack app deployable on Alibaba Cloud ECS, using Qwen server-side only, with real database tools and human-in-the-loop guardrails.

## Alternate Prompt

`Flour and milk are low. Weekend sales may be high.`

## Current Limitations

- No POS integration.
- No Excel import/export.
- Supplier emails are not delivered in demo mode.
- Reasoning Activity is structured explainability, not raw chain-of-thought.
