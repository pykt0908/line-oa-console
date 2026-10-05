# Google Drive Storage Setup

รูป/ไฟล์จาก LINE จะถูกดาวน์โหลดแล้วอัปโหลดขึ้น Google Drive  
DB เก็บแค่ metadata + Google file id ใน `attachments.storage_path`

**ไม่ต้องสร้าง Google Cloud Project ใหม่** — ใช้ project / service account เดิมจากระบบเก่าได้เลย

## แบบที่ใช้อยู่แล้ว (Service Account — แนะนำ)

ค่าจากระบบเก่าใช้ได้ตรง ๆ:

```env
GOOGLE_DRIVE_ENABLED=true
GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON=/absolute/path/to/google-service-account.json
GOOGLE_DRIVE_SHARED_DRIVE_ID=
GOOGLE_DRIVE_ROOT_FOLDER_ID=
```

หรือใช้ชื่อใหม่ที่เทียบเท่า:

| เดิม | ใหม่ (alias) |
|------|----------------|
| `GOOGLE_DRIVE_ROOT_FOLDER_ID` | `GOOGLE_DRIVE_FOLDER_ID` |
| `GOOGLE_DRIVE_ENABLED=true` | หรือตั้ง `FILESYSTEM_DISK=google-drive` |
| `GOOGLE_DRIVE_SHARED_DRIVE_ID` | ใส่ถ้าใช้ Shared Drive (ว่างได้) |

### ขั้นตอนบนเครื่องนี้

1. คัดลอกไฟล์ `google-service-account.json` จากเซิร์ฟเวอร์เก่ามาวาง เช่น  
   `backend/storage/app/google-service-account.json` (**อย่า commit**)
2. ใน `backend/.env`:

```env
GOOGLE_DRIVE_ENABLED=true
GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON=/Users/panya/Documents/line-chat-archive/backend/storage/app/google-service-account.json
GOOGLE_DRIVE_SHARED_DRIVE_ID=
GOOGLE_DRIVE_ROOT_FOLDER_ID=xxxxx
```

3. โฟลเดอร์ Drive ต้องแชร์ให้ email ของ service account (สิทธิ์ Editor)  
   เปิด JSON ดูคีย์ `client_email` เช่น `xxx@....iam.gserviceaccount.com`
4. `GOOGLE_DRIVE_ROOT_FOLDER_ID` = id หลัง `/folders/` ใน URL โฟลเดอร์  
   ถ้าใช้ Shared Drive เป็นราก ใส่ `GOOGLE_DRIVE_SHARED_DRIVE_ID` แทนได้

### ทดสอบ

```bash
cd backend
php artisan config:clear
php artisan drive:test
```

ควรขึ้น `Google Drive OK`

ในหน้า Settings ตั้ง `storage.driver = google-drive` (หรือพึ่ง `GOOGLE_DRIVE_ENABLED=true`)

## แบบ OAuth (บัญชี Google ปกติ — ไม่บังคับ)

ใช้เมื่อไม่มี service account:

```env
FILESYSTEM_DISK=google-drive
GOOGLE_DRIVE_CLIENT_ID=xxxx.apps.googleusercontent.com
GOOGLE_DRIVE_CLIENT_SECRET=xxxx
GOOGLE_DRIVE_REFRESH_TOKEN=xxxx
GOOGLE_DRIVE_FOLDER_ID=1AbCxxxxx
```

รายละเอียดขอ token: OAuth Playground + scope `https://www.googleapis.com/auth/drive.file`

## กลับไปใช้ local

```env
GOOGLE_DRIVE_ENABLED=false
FILESYSTEM_DISK=local
```

Settings: `storage.driver = local`

## หมายเหตุ shared hosting

- ต้องให้ PHP เรียกออกเน็ตถึง `googleapis.com` ได้
- path ของ JSON บน hosting ใช้ path จริงของเซิร์ฟเวอร์ได้เหมือนระบบเก่า
- Backup: สำรองทั้ง MySQL และโฟลเดอร์ Drive
