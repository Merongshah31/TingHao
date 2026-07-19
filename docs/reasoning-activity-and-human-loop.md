# Reasoning Activity And Human-In-The-Loop Guardrails

Date: 2026-06-29

## Summary

Phase 4.5 adds structured Reasoning Activity for TingHao Agent. It gives admins and judges a clear explanation timeline without exposing raw model chain-of-thought.

The timeline uses safe step types:

```text
Observe -> Understand -> Plan -> Tool Action -> Tool Result -> Decision Summary -> Human Checkpoint
```

## What Is Stored

- Concise observations.
- Parsed intent summaries.
- Decision factors.
- Tool action/result summaries.
- Evidence JSON from visible app data.
- Confidence when available.
- Risk level.
- Human checkpoint labels.
- Links to related tool calls.

## What Is Not Stored

- Raw model chain-of-thought.
- Hidden reasoning.
- Qwen API keys.
- Frontend JavaScript prompts or model secrets.

## Database

New table:

- `agent_reasoning_steps`

Important fields:

- `agent_run_id`
- `step_order`
- `step_type`
- `title`
- `summary`
- `evidence`
- `confidence`
- `risk_level`
- `requires_human_approval`
- `related_tool_call_id`

## Guardrails

`HumanApprovalGuardService` centralizes critical approval rules.

The agent may create draft records, supplier email drafts, expiry loss recommendations, and RM loss calculations.

The agent may not approve purchase orders, approve or mark supplier emails sent, remove expired stock, receive purchase order stock, complete expiry recommendations, or change stock quantity without a human action.

## UI

Reasoning Activity appears on agent run, purchase order, supplier email draft, and expiry loss recommendation detail pages.

Staff users can see waiting states, but cannot approve or execute protected actions.

## Track 4 Value

This supports Track 4 Autopilot Agent production-readiness by showing ambiguous input handling, tool invocation, visible decision summaries, human checkpoints, and auditability without exposing private chain-of-thought.
