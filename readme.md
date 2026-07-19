# TingHao Agent

Qwen-powered Autopilot Procurement Agent for small bakery inventory operations.

Track: Qwen Cloud Hackathon Track 4: Autopilot Agent

## What It Does

TingHao Agent turns messy staff messages and inventory alerts into structured restock plans, purchase order drafts, supplier email drafts, expiry-loss recommendations, and human-approved business actions.

## Features

- Smart Procurement Inbox for ambiguous staff/supplier messages.
- Autonomous Restock Engine that checks real inventory and drafts purchase orders.
- Supplier Email Draft workflow with admin approval and demo-safe mark-sent state.
- Expiry Loss Prevention with RM impact calculation.
- Reasoning Activity timeline with safe summaries, evidence, confidence, risk labels, and human checkpoints.
- Human-in-the-loop guardrails for critical actions.
- `/demo`, `/health`, and `/agent/proof` pages/endpoints for judges.

## Architecture

This is a Laravel full-stack app:

- Frontend: Laravel Blade views.
- Backend: Laravel controllers, services, Eloquent models.
- Database: Supabase PostgreSQL by default, or Alibaba Cloud RDS PostgreSQL if moved for production.
- Deployment target: Alibaba Cloud ECS running Docker, Nginx, PHP-FPM, and Laravel.
- AI: Qwen Cloud / Alibaba Cloud Model Studio through `app/Services/Qwen/QwenClient.php`.

Qwen is called server-side only. API keys are never exposed to Blade or frontend JavaScript.

## Demo Accounts

- Admin: `admin@tinghao.com` / `password`
- Staff: `staff@tinghao.com` / `password`

## Demo Steps

1. Open `/demo`.
2. Login as staff.
3. Open `/agent`.
4. Submit: `gula dah abis boss, nak order 50kg dari Supplier Ali tak?`
5. Review parsed intent, inventory lookup, supplier ranking, Reasoning Activity, and tool calls.
6. Open the linked PO draft.
7. Login as admin.
8. Approve the PO.
9. Generate supplier email draft.
10. Approve and mark sent.
11. Run `/agent/expiry-loss`.
12. Open `/health` and `/agent/proof`.

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

## Environment Variables

```env
QWEN_API_KEY=
QWEN_BASE_URL=https://dashscope-intl.aliyuncs.com/compatible-mode/v1
QWEN_MODEL=qwen-plus
QWEN_MOCK_MODE=true
```

For production:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `LOG_LEVEL=warning`
- `QWEN_MOCK_MODE=false` when using a real Qwen key

## Testing Commands

```bash
php artisan optimize:clear
php artisan route:list
php artisan test
npm run build
```

## Security Notes

- Qwen API keys are server-side only.
- `/health` and `/agent/proof` expose safe booleans and feature metadata only.
- No real supplier email is sent by the demo-safe workflow.
- Critical actions require admin approval.
- Reasoning Activity is structured explainability, not raw chain-of-thought.

## Documentation

- `docs/current-function-inventory.md`
- `docs/prd.md`
- `docs/architecture.md`
- `docs/qwen-usage.md`
- `docs/alibaba-cloud-deployment-proof.md`
- `docs/devpost-submission-draft.md`
- `docs/demo-script.md`
- `docs/backend-api.md`
- `docs/database.md`
- `docs/ui-guide.md`
- `docs/TODO.md`

## License

MIT License. See `LICENSE`.
