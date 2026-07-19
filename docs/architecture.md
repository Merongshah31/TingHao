# TingHao Agent Architecture

Last updated: 2026-06-29

TingHao Agent is a Laravel full-stack application. Blade views are the frontend, Laravel controllers/services are the backend, and Qwen Cloud is called only from server-side Laravel code.

## Deployment Shape

```mermaid
flowchart TD
    browser["Admin / Staff Browser"]
    ecs["Alibaba Cloud ECS\nDocker + Nginx + PHP-FPM + Laravel"]
    blade["Blade Frontend"]
    controllers["Laravel Controllers"]
    agent["TingHao Agent Services"]
    qwenClient["QwenClient\napp/Services/Qwen/QwenClient.php"]
    qwen["Qwen Cloud / Alibaba Cloud Model Studio"]
    inventory["Inventory Tool"]
    supplier["Supplier Ranking Tool"]
    po["Purchase Order Tool"]
    email["Supplier Email Draft Tool"]
    expiry["Expiry Loss Tool"]
    guard["Human Approval Guard"]
    reasoning["Reasoning Activity Timeline"]
    db["PostgreSQL Database\nSupabase PostgreSQL or Alibaba Cloud RDS"]

    browser --> ecs
    ecs --> blade
    blade --> controllers
    controllers --> agent
    agent --> qwenClient
    qwenClient --> qwen
    agent --> inventory
    agent --> supplier
    agent --> po
    agent --> email
    agent --> expiry
    agent --> guard
    agent --> reasoning
    inventory --> db
    supplier --> db
    po --> db
    email --> db
    expiry --> db
    reasoning --> db
    guard --> db
```

## Core Rules

- Frontend and backend live in the same Laravel application.
- Blade renders UI; no separate frontend app is required.
- Qwen API keys are read from environment variables and never sent to Blade or JavaScript.
- `QwenClient` calls Qwen Cloud / Alibaba Cloud Model Studio server-side only.
- Laravel executes real business actions through internal tools and Eloquent models.
- Human approval is required before critical actions such as PO approval, supplier email approval, expiry recommendation completion, expired stock removal, and PO receiving.
- Reasoning Activity stores concise summaries, evidence, confidence, risk labels, and human checkpoints. It does not expose raw chain-of-thought.

## Agent Workflow

```text
Smart Procurement Inbox
  -> AgentController
  -> TingHaoAgentService
  -> ProcurementMessageParserService
  -> QwenClient or mock fallback
  -> InventoryLookupToolService
  -> RestockPlanningService
  -> SupplierRankingService
  -> PurchaseOrderDraftService
  -> ApprovalRequest
  -> SupplierEmailDraftService after admin approval
  -> ReasoningActivityService and AgentToolCall audit logs
```

## Expiry Loss Workflow

```text
Dashboard or /agent/expiry-loss
  -> ExpiryLossRecommendationController
  -> ExpiryLossPreventionService
  -> Query ingredients expiring within 7 days
  -> Calculate potential RM loss from real quantity and cost
  -> QwenClient or mock fallback for practical recommendation
  -> Save expiry_loss_recommendations
  -> Show RM impact and human-review actions
```

## Proof Endpoints

- `/health` returns safe service, architecture, database, Qwen configuration, and mock-mode status.
- `/agent/proof` returns safe hackathon proof metadata for Alibaba Cloud ECS and server-side Qwen use.
- Neither endpoint exposes API keys, database credentials, or secret environment values.
