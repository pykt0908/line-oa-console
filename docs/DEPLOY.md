# Deploy Guide — Shared Hosting

## Requirements

- PHP 8.2+ (8.4 recommended)
- MySQL / MariaDB
- Composer
- Node 20+ (build machine only)
- Cron

## Build

```bash
cd backend && composer install --no-dev --optimize-autoloader
cd ../frontend && npm ci && npm run build
```

Vite writes assets to `backend/public/app/`.

## Environment

Copy `.env.example` values. Important:

```
APP_URL=https://your-domain.example
SESSION_DOMAIN=your-domain.example
SANCTUM_STATEFUL_DOMAINS=your-domain.example
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
# Optional override if media must be fetched from another public HTTPS host/tunnel
LINE_PUBLIC_BASE_URL=https://your-domain.example
```

Encrypt LINE secrets automatically via Eloquent `encrypted` cast — set a stable `APP_KEY`.

LINE จะดึงรูป/วิดีโอที่ส่งออกจาก URL สาธารณะ (HTTPS) — ในเครื่อง local ต้องตั้ง `LINE_PUBLIC_BASE_URL` เป็น URL tunnel ที่ชี้มาที่ backend

## Document root

Point the vhost/document root to `backend/public`.

## Cron

```bash
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler runs `queue:work --stop-when-empty`, attachment retries, and retention cleanup.

## LINE Webhook

For each OA in the console, copy the webhook URL:

```
https://your-domain.example/api/webhooks/line/{uuid}
```

## Backup / Restore

```bash
./scripts/backup-mysql.sh
gunzip -c backups/line_chat_archive_YYYYMMDD_HHMMSS.sql.gz | mysql -u root line_chat_archive
```

## Local development

```bash
# Terminal 1 — API
cd backend && php artisan serve

# Terminal 2 — queue (optional while developing; cron handles prod)
cd backend && php artisan queue:work

# Terminal 3 — frontend
cd frontend && npm run dev

# Terminal 4 — public webhook tunnel (LINE → local)
./scripts/dev-webhook-tunnel.sh
# or: brew install cloudflare/cloudflare/cloudflared
#     cloudflared tunnel --url http://localhost:8000
```

Copy the tunnel HTTPS URL into each OA webhook field:

```
https://<tunnel-host>/api/webhooks/line/<lineAccountUuid>
```

Login: `admin@example.com` / `password`

See also [MVP_CHECKLIST.md](MVP_CHECKLIST.md) and [GOOGLE_DRIVE.md](GOOGLE_DRIVE.md).

