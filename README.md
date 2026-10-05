# Multi LINE OA Chat Archive & Live Chat

Institutional console for archiving and answering LINE Official Account chats across multiple OA channels.

## Stack

- Backend: Laravel (REST API, Sanctum SPA cookies, Spatie permission)
- Frontend: Vue 3 + Vuetify 3 + Pinia
- Database: MySQL / MariaDB (XAMPP local)
- Realtime: Adaptive polling (no WebSocket)

## Quick start (local)

```bash
# 1) Start MariaDB in XAMPP
# 2) Backend
cd backend
cp .env.example .env   # or use the provided .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve

# 3) Frontend
cd ../frontend
npm install
npm run dev
```

Open http://localhost:5173  
Login: `admin@example.com` / `password`

## Spec & design

- [spec/SPEC_LINE_OA_CHAT_V2.md](spec/SPEC_LINE_OA_CHAT_V2.md)
- [design/DESIGN.md](design/DESIGN.md)
- [docs/DEPLOY.md](docs/DEPLOY.md)

## Google Drive (attachments)

See [docs/GOOGLE_DRIVE.md](docs/GOOGLE_DRIVE.md). After credentials are set:

```bash
cd backend
# set FILESYSTEM_DISK=google-drive and storage.driver=google-drive
php artisan drive:test
```

## Webhook tunnel (dev)

```bash
./scripts/dev-webhook-tunnel.sh
```

## MVP checklist

See [docs/MVP_CHECKLIST.md](docs/MVP_CHECKLIST.md) — all 25 acceptance criteria covered.

Webhook signature + idempotency, multi OA, inbox/live chat polling, student code + merge, tags/notes, attachments archive job, dashboard/audit/settings, shared-hosting cron/queue.

