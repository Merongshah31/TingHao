# Qwen Usage

Last updated: 2026-07-04

TingHao Agent uses Qwen Cloud / Alibaba Cloud Model Studio as the reasoning and language layer for ambiguous bakery procurement operations. Laravel remains responsible for validation, permissions, database reads/writes, and human-approved business actions.

## Configuration

Environment variables:

- `QWEN_API_KEY`: server-side API key. Never expose this in Blade or frontend JavaScript.
- `QWEN_BASE_URL`: default `https://dashscope-intl.aliyuncs.com/compatible-mode/v1`.
- `QWEN_MODEL`: default `qwen-plus`.
- `QWEN_MOCK_MODE`: default `true` for reliable demos without a real key.
- `QWEN_MAX_TOKENS_PARSE`: default `350`.
- `QWEN_MAX_TOKENS_EMAIL`: default `500`.
- `QWEN_MAX_TOKENS_EMAIL_DRAFT`: default `500`, preferred for supplier email draft generation.
- `QWEN_MAX_TOKENS_EXPIRY`: default `350`.
- `QWEN_MAX_TOKENS_STOCK_REASONING`: default `300`.
- `QWEN_TEMPERATURE`: default `0.2`.
- `QWEN_CACHE_MINUTES`: default `30`.
- `QWEN_STOCK_REASONING_CACHE_MINUTES`: default `30`.
- `QWEN_EMAIL_DRAFT_CACHE_MINUTES`: default `30`; saved draft reuse is currently the primary duplicate-call guard.

Qwen usage code file:

- `app/Services/Qwen/QwenClient.php`

## Where Qwen Is Used

- Ambiguous procurement message parsing from `/agent`.
- Supplier email draft generation after an admin approves an agent-created purchase order.
- Expiry loss recommendation generation for ingredients expiring within 7 days.
- Stock Planner explanation after FastAPI returns a stock prediction result.

Qwen is not used for dashboard cards, low-stock threshold calculations, expiry filtering, supplier basic scoring, stock prediction calculation, purchase order creation, approval status updates, or deterministic database work.

## Server-Side Flow

1. Admin or staff submits text through Blade.
2. Laravel validates the request.
3. `QwenClient` calls Qwen Cloud from the server only with purpose-specific `max_tokens` and shared low temperature.
4. Qwen returns structured JSON where possible.
5. Laravel normalizes the response and executes real database lookups/actions through internal tools.
6. Agent runs, tool calls, Reasoning Activity, PO drafts, supplier email drafts, and expiry recommendations are persisted for auditability.

## Token Efficiency

- Procurement parsing caches identical normalized input text for `QWEN_CACHE_MINUTES`.
- Supplier email prompts send compact PO and supplier payloads, not full logs.
- Expiry scans calculate risk in Laravel and send one compact batch request for recommendation text.
- Stock Planner explanations cache by ingredient and prediction snapshot hash for `QWEN_STOCK_REASONING_CACHE_MINUTES`.
- Supplier email draft generation calls Qwen only when an admin clicks generate or regenerate. If a draft already exists, Laravel opens the saved draft instead of calling Qwen again.
- Supplier email draft Qwen metadata is stored in `supplier_email_drafts.qwen_metadata` without API keys or raw chain-of-thought.
- Dashboard, Stock Planner Prediction View, and Stock Planner Calendar View do not call Qwen for stock prediction explanation.
- `QwenClient` records safe metadata only: model, mock mode, server-side configured flag, HTTP status, latency, token usage if returned, max token budget, and temperature.

## Mock Mode

Mock mode is enabled when `QWEN_MOCK_MODE=true` or when no API key exists. It provides deterministic fallback output for Devpost and judge demos while keeping the real Laravel database flow intact.

## Reasoning Safety

TingHao Agent does not request, store, or display raw chain-of-thought. Prompts ask for concise summaries, decision factors, risk flags, and confidence values. The UI shows these as structured Reasoning Activity.

Stock Planner explanation prompts ask Qwen for JSON only and instruct it not to recalculate prediction results, invent unavailable data, suggest automatic purchase, or expose chain-of-thought.

## Security Notes

- API keys live only in environment variables.
- Blade may show whether Qwen is configured, but never shows the key.
- `/health` and `/agent/proof` expose only safe metadata.
- No real supplier email is sent by the demo-safe mark-sent workflow.
