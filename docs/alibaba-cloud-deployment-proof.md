# Alibaba Cloud Deployment Proof

Last updated: 2026-06-29

## Target

- Backend target: Alibaba Cloud ECS
- Runtime: Docker, Nginx, PHP-FPM, Laravel
- Frontend: Laravel Blade inside the same full-stack app
- Database: Supabase PostgreSQL for current setup, or Alibaba Cloud RDS PostgreSQL if moved for production
- AI: Qwen Cloud / Alibaba Cloud Model Studio

## Important Files

- Docker runtime: `Dockerfile`
- Nginx config template: `docker/nginx.conf.template`
- PHP config: `docker/php.ini`
- Startup script: `scripts/render-start.sh`
- Qwen usage code: `app/Services/Qwen/QwenClient.php`
- Health endpoint: `/health`
- Proof endpoint: `/agent/proof`

## Safe Proof Endpoints

`/health` returns safe JSON:

- service name
- architecture
- Track 4 label
- database connection status
- Qwen configured boolean
- mock mode boolean

`/agent/proof` returns safe JSON:

- Alibaba Cloud ECS backend target
- Qwen server-side confirmation
- API key exposure flag set to false
- implemented agent feature list

Neither endpoint exposes API keys, database credentials, or secret environment values.

## Suggested Proof Recording Steps

1. Open Alibaba Cloud console.
2. Show ECS instance running.
3. SSH into the server.
4. Show Docker container running.
5. Open `/health`.
6. Open `/agent/proof`.
7. Run the TingHao Agent workflow:
   - staff submits messy restock message
   - agent creates PO draft
   - admin approves
   - admin generates supplier email draft
   - expiry loss scan shows RM impact

## Environment Notes

Set these values on ECS:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `LOG_LEVEL=warning`
- `QWEN_API_KEY=<server-side key>`
- `QWEN_BASE_URL=https://dashscope-intl.aliyuncs.com/compatible-mode/v1`
- `QWEN_MODEL=qwen-plus`
- `QWEN_MOCK_MODE=false` for live Qwen, or `true` for stable demo mode

Do not place secret values in Blade, JavaScript, screenshots, or public repository files.
