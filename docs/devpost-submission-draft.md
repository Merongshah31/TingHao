# Devpost Submission Draft

## Project Name

TingHao Agent

## Tagline

Qwen-powered Autopilot Procurement Agent for small bakery inventory operations.

## Track

Qwen Cloud Hackathon Track 4: Autopilot Agent

## Inspiration

Small bakery teams often manage stock through messy staff messages, supplier calls, and manual checks. Low stock and near-expiry ingredients can become missed orders, wasted RM value, or delayed production.

## What It Does

TingHao Agent turns messy staff messages and inventory alerts into structured restock plans, purchase order drafts, supplier email drafts, expiry-loss recommendations, and human-approved business actions.

## How We Built It

The project is a Laravel full-stack app. Blade renders the UI, Laravel controllers coordinate requests, Eloquent models persist operational records, and Agent services call Qwen plus internal Laravel tools for inventory, suppliers, purchase orders, email drafts, expiry loss, reasoning activity, and guardrails.

## How Qwen Cloud Is Used

Qwen is used server-side for ambiguous procurement parsing, supplier email draft generation, and expiry-loss recommendation generation. Qwen returns structured JSON where possible. Laravel executes real business actions and stores audit logs.

## How Alibaba Cloud Is Used

The target backend deployment is Alibaba Cloud ECS running Docker, Nginx, PHP-FPM, and Laravel. Qwen Cloud / Alibaba Cloud Model Studio provides the AI layer. `/health` and `/agent/proof` provide safe deployment proof for judges.

## Challenges We Ran Into

- Keeping agent automation useful without bypassing human approvals.
- Making Qwen demos reliable with mock mode while preserving real database flow.
- Showing reasoning transparently without exposing raw chain-of-thought.
- Optimizing demo-critical pages without rewriting the app.

## Accomplishments

- Smart Procurement Inbox for messy multilingual messages.
- Autonomous Restock Engine that drafts purchase orders.
- Supplier email draft workflow with demo-safe mark-sent state.
- Expiry Loss Prevention with measurable RM impact.
- Reasoning Activity and human-in-the-loop guardrails.
- Devpost-ready demo guide, proof endpoints, architecture docs, and demo script.

## What We Learned

Autopilot agents are most useful when they combine AI interpretation with deterministic internal tools, permission checks, and auditable human checkpoints.

## What's Next

- Production SMTP after approval rules are reviewed.
- Optional recipe/POS integration after schema requirements are confirmed.
- Richer expiry recommendation templates if recipe data is added.
- More Qwen parsing examples and evaluation tests.

## Testing Instructions

1. Open `/demo`.
2. Login as staff: `staff@tinghao.com` / `password`.
3. Open `/agent`.
4. Submit: `gula dah abis boss, nak order 50kg dari Supplier Ali tak?`
5. Review reasoning, tool calls, supplier ranking, and PO draft.
6. Login as admin: `admin@tinghao.com` / `password`.
7. Approve PO, generate supplier email draft, approve and mark sent.
8. Run expiry loss scan.
9. Open `/health` and `/agent/proof`.

## Demo Accounts

- Admin: `admin@tinghao.com` / `password`
- Staff: `staff@tinghao.com` / `password`

## Repository Structure

- `app/Http/Controllers`: Laravel web controllers
- `app/Services/Agent`: TingHao Agent tools and workflows
- `app/Services/Qwen/QwenClient.php`: Qwen Cloud client
- `app/Models`: Eloquent models
- `resources/views`: Blade frontend
- `database/migrations`: schema
- `database/seeders`: demo data
- `docs`: product, architecture, deployment, and demo docs

## Architecture Summary

Admin/staff use a browser to access the Laravel app on Alibaba Cloud ECS. Blade renders the frontend. Laravel controllers call agent services. Qwen is called server-side only. Internal tools query PostgreSQL and create PO drafts, email drafts, expiry recommendations, Reasoning Activity, and approval checkpoints.
