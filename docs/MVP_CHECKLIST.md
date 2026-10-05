# MVP Acceptance Checklist (SPEC §50)

| # | Criterion | Status | Evidence |
|---|-----------|--------|----------|
| 1 | เพิ่ม LINE OA ≥ 2 บัญชี | Done | Seeder creates 3 OAs; UI `LineAccountsView` |
| 2 | แต่ละ OA รับ webhook ได้ | Done | `POST /api/webhooks/line/{uuid}` |
| 3 | Webhook ตรวจ signature | Done | `LineWebhookService::verifySignature` + `WebhookTest` |
| 4 | Contact ใหม่ถูกสร้างอัตโนมัติ | Done | `ContactService::findOrCreateIdentity` |
| 5 | ไม่มี student_code ก็สนทนาได้ | Done | `student_code` nullable |
| 6 | Staff เพิ่ม student_code ได้ | Done | `POST /contacts/{id}/student-code` + sidebar form |
| 7 | ไม่บังคับระดับ/ห้อง/สาขา | Done | Schema ไม่มี fields เหล่านี้ |
| 8 | Merge Contact หลาย OA | Done | `ContactService::merge` + UI merge dialog |
| 9 | ประวัติไม่หายหลัง merge | Done | `ContactMergeTest` |
| 10 | Inbox รวมหลาย OA | Done | `ConversationService::listForUser` |
| 11 | Filter ตาม OA | Done | `line_account_id` filter + UI select |
| 12 | Staff ตอบ text จาก Dashboard | Done | Push API via `sendOutboundText` |
| 13 | Chat update ผ่าน polling | Done | `useAdaptivePolling` |
| 14 | ไม่มี duplicate เมื่อ webhook retry | Done | Unique keys + `WebhookTest` |
| 15 | Search student_code | Done | Inbox/Contacts `q` filter |
| 16 | Search LINE name | Done | Inbox/Contacts `q` filter |
| 17 | Search message history | Done | LIKE + FULLTEXT on `text_content` |
| 18 | Internal Note | Done | Notes API + yellow cards in timeline |
| 19 | Tag | Done | Tags API + sidebar chips |
| 20 | Assignment | Done | `/assign` + header control |
| 21 | Open/Pending/Resolved | Done | Status patch + chips |
| 22 | Staff เห็นเฉพาะ OA ที่มีสิทธิ์ | Done | `accessibleLineAccountIds()` on queries |
| 23 | Attachment archive | Done | `ArchiveLineAttachment` job + scheduler retry |
| 24 | Shared Hosting ไม่ใช้ WebSocket | Done | Polling + DB queue + cron |
| 25 | Audit Log | Done | `AuditService` + `/audit-logs` UI |

## Local verify commands

```bash
cd backend && php artisan test --filter='WebhookTest|ContactMergeTest'
cd frontend && npm run build
./scripts/backup-mysql.sh
```
