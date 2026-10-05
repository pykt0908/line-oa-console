# SPEC.md

# Multi LINE OA Chat Archive & Live Chat

**Version:** 2.0\
**Target Environment:** Shared Hosting\
**Frontend:** Vue 3 + Vuetify 3\
**Backend:** Laravel 12 REST API\
**Database:** MySQL / MariaDB\
**Realtime Strategy:** Adaptive Polling\
**Primary Use Case:** รับ--ตอบ--ค้นหา--เก็บประวัติแชท LINE OA ระยะยาว
รองรับหลาย LINE OA

------------------------------------------------------------------------

## 1. แนวคิดของระบบ

ระบบนี้เป็นศูนย์กลางสำหรับบริหารการสนทนาจาก LINE Official Account หลายบัญชีใน Web
Dashboard เดียว

นักศึกษา/ผู้ติดต่อยังคงส่งข้อความผ่าน LINE ตามปกติ ส่วนอาจารย์หรือเจ้าหน้าที่สามารถเปิด Web
Dashboard เพื่ออ่านและตอบข้อความได้

ระบบต้องบันทึกข้อความที่ได้รับผ่าน LINE Messaging API ตั้งแต่วันที่เริ่มใช้งานระบบ
เพื่อให้สามารถค้นหาและเปิดดูย้อนหลังได้ แม้ข้อความเก่าจะไม่สะดวกต่อการเข้าถึงจาก LINE OA
Manager แล้ว

ระบบ **ไม่มีฐานข้อมูลนักศึกษาเดิม** และไม่พยายามสร้าง Student Information System

ผู้ใช้ LINE ที่ทักเข้ามาจะถูกสร้างเป็น `LINE Contact` อัตโนมัติ และสามารถเพิ่ม
`student_code` ภายหลังเพื่อระบุว่าเป็นนักศึกษารหัสใด

------------------------------------------------------------------------

## 2. หลักการสำคัญ

1.  LINE Contact คือ identity เริ่มต้น
2.  `line_user_id` ใช้ระบุผู้ใช้ภายใน LINE OA/Provider context
3.  `student_code` เป็นข้อมูลเสริมสำหรับระบุตัวตนและรวมประวัติ
4.  ไม่ใช้ LINE Display Name เป็น unique identity
5.  ไม่บังคับให้มี student_code เพื่อรับหรือเก็บข้อความ
6.  ไม่เก็บระดับชั้น ห้อง หรือสาขา
7.  คนทั่วไปที่ไม่มีรหัสนักศึกษายังคุยกับระบบได้
8.  นักศึกษาคนเดียวสามารถมี LINE identity หลายรายการจากหลาย OA
9.  ระบบต้องรองรับ Multi LINE OA ตั้งแต่ต้น
10. ระบบต้องทำงานบน Shared Hosting โดยไม่ต้องใช้ WebSocket, Redis หรือ
    Supervisor

------------------------------------------------------------------------

## 3. Goals

-   รับ Webhook จาก LINE OA
-   รองรับ LINE OA หลายบัญชี
-   เก็บข้อความลงฐานข้อมูลระยะยาว
-   แสดง Inbox รวมทุก OA
-   กรอง Inbox ตาม OA
-   ตอบข้อความจาก Web Dashboard
-   Update หน้าแชทอัตโนมัติโดยไม่ refresh
-   เก็บรูปและไฟล์แนบ
-   ค้นหาด้วย LINE Display Name
-   ค้นหาด้วยรหัสนักศึกษา
-   ค้นหาข้อความย้อนหลัง
-   เพิ่ม/แก้ไขรหัสนักศึกษาให้ Contact
-   รวม Contact หลาย OA ด้วย student_code เดียวกัน
-   Assign conversation ให้เจ้าหน้าที่
-   Internal Note
-   Tag
-   Open / Pending / Resolved
-   RBAC
-   Audit Log

------------------------------------------------------------------------

## 4. Non-Goals

Version 1 ไม่ทำ:

-   ระบบทะเบียนนักศึกษา
-   ฐานข้อมูลระดับชั้น
-   ฐานข้อมูลห้อง
-   ฐานข้อมูลสาขาวิชา
-   ระบบคะแนน
-   ระบบวิชาการ
-   Video Call
-   Voice Call
-   ดึงข้อความย้อนหลังที่เกิดก่อนติดตั้งระบบ
-   จำลอง LINE OA Manager ทุก feature

------------------------------------------------------------------------

## 5. Architecture

``` text
┌─────────────────┐
│ นักศึกษา/ผู้ใช้ │
│    LINE App     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│     LINE OA     │
└────────┬────────┘
         │
         │ Messaging API
         │ Webhook
         ▼
┌──────────────────────────────────────┐
│          Shared Hosting              │
│                                      │
│ Laravel 12 REST API                  │
│                                      │
│ ├── Webhook Handler                  │
│ ├── LINE Messaging Service           │
│ ├── Contact Service                  │
│ ├── Conversation Service             │
│ ├── Attachment Service               │
│ └── Authentication / RBAC            │
│                                      │
│            MySQL                     │
└─────────────────┬────────────────────┘
                  │
                  │ REST API
                  │ Adaptive Polling
                  ▼
┌──────────────────────────────────────┐
│ Vue 3 + Vuetify                     │
│                                      │
│ Dashboard                            │
│ Inbox                                │
│ Live Chat                            │
│ Contacts                             │
│ LINE OA Settings                     │
└──────────────────────────────────────┘
```

------------------------------------------------------------------------

## 6. Shared Hosting Constraints

ระบบต้องไม่บังคับใช้:

-   Redis
-   Laravel Reverb
-   WebSocket Server
-   Node.js runtime ใน production
-   Supervisor
-   Queue Worker ที่ต้องทำงานตลอดเวลา

ใช้:

-   Laravel REST API
-   MySQL
-   Adaptive Polling
-   Laravel Scheduler
-   Cron Job
-   Database Queue เฉพาะเมื่อจำเป็น

Frontend Vue ต้อง build ก่อน deploy

------------------------------------------------------------------------

# 7. Multi LINE OA

ระบบต้องเพิ่ม LINE OA ได้หลายบัญชี

ตัวอย่าง:

-   งานวิชาการ
-   งานทะเบียน
-   งานการเงิน
-   IT Support
-   ประชาสัมพันธ์

## line_accounts

``` text
id
uuid
name
slug
channel_id
channel_secret
channel_access_token
bot_user_id nullable
account_color nullable
is_active
last_webhook_at nullable
created_at
updated_at
```

`channel_secret` และ `channel_access_token` ต้องเข้ารหัสก่อนเก็บ

Webhook URL:

``` text
POST /api/webhooks/line/{lineAccountUuid}
```

ระบบต้องรู้ว่า event มาจาก OA ใดก่อน process

------------------------------------------------------------------------

# 8. Contact Model

ไม่มี Student Master Database

เมื่อมี LINE User ทักเข้ามาครั้งแรก ระบบสร้าง Contact อัตโนมัติ

## contacts

``` text
id
student_code nullable
contact_type
note nullable
created_at
updated_at
```

`contact_type`:

``` text
student
other
unknown
```

### student_code

-   nullable
-   ใช้ค้นหา Contact
-   ใช้รวม LINE identities
-   ไม่ใช้เป็น Primary Key
-   ไม่ควรบังคับ unique ในระดับ database หากยังต้องรองรับการแก้ข้อมูลผิด/merge
    workflow
-   application layer ต้องตรวจ duplicate และเสนอ merge/link อย่างปลอดภัย

ระบบไม่เก็บ:

``` text
level
class
room
department
```

------------------------------------------------------------------------

# 9. LINE Identity

## line_identities

``` text
id
contact_id
line_account_id
line_user_id
display_name nullable
picture_url nullable
status_message nullable
first_seen_at
last_seen_at
blocked_at nullable
created_at
updated_at
```

Unique:

``` text
(line_account_id, line_user_id)
```

หมายเหตุ:

LINE User ID ไม่ควรถูกใช้เป็น global student identity ข้ามทุก OA/Provider

ระบบใช้ Contact เป็น identity กลาง

------------------------------------------------------------------------

# 10. Contact Creation Flow

เมื่อได้รับข้อความ:

``` text
Webhook
   ↓
หา LINE Account
   ↓
หา line_identity
   ↓
ไม่พบ
   ↓
สร้าง Contact
   ↓
สร้าง line_identity
   ↓
student_code = NULL
   ↓
สร้าง Conversation
   ↓
บันทึก Message
```

ตัวอย่าง:

``` text
Contact #125

LINE Name: Bank
Student Code: -
Status: ยังไม่ระบุรหัสนักศึกษา
```

ระบบต้องไม่ปฏิเสธข้อความเพราะไม่มี student_code

------------------------------------------------------------------------

# 11. Student Code Identification

เจ้าหน้าที่สามารถเพิ่มรหัสนักศึกษาให้ Contact ได้

ตัวอย่าง:

``` text
LINE Name
Bank

รหัสนักศึกษา
[ 68001234 ]

[ บันทึก ]
```

หลังบันทึก:

``` text
Bank
68001234
```

ไม่จำเป็นต้องมีชื่อจริง ระดับ ห้อง หรือสาขา

------------------------------------------------------------------------

# 12. Optional Self Identification

สามารถเปิด/ปิด feature นี้จาก Settings

เมื่อ Contact ใหม่ทักมา ระบบสามารถตอบอัตโนมัติ:

``` text
เพื่อความสะดวกในการค้นหาประวัติการติดต่อ
กรุณาระบุรหัสนักศึกษาของคุณ
```

Version แรกสามารถใช้:

-   Rich Menu / Flex Message + Web Form
-   LIFF ในอนาคต
-   Staff กรอกให้เอง

หากผู้ใช้ไม่กรอก ระบบยังสนทนาได้ตามปกติ

สถานะต้องใช้คำว่า:

``` text
ยังไม่ระบุรหัสนักศึกษา
ระบุรหัสแล้ว
```

ไม่ควรใช้คำว่า "ยืนยันตัวตนแล้ว" หากไม่มีฐานข้อมูลต้นทางสำหรับ verify

------------------------------------------------------------------------

# 13. Same Student Across Multiple OA

นักศึกษาคนเดียวอาจติดต่อหลาย LINE OA

``` text
Contact
Student Code: 68001234

        │
        ├── OA งานวิชาการ
        │      UxxxxA
        │
        ├── OA งานทะเบียน
        │      UxxxxB
        │
        └── OA IT
               UxxxxC
```

ทุก `line_identity` สามารถชี้ไป Contact เดียวกัน

ระบบต้องมี Merge Contact

------------------------------------------------------------------------

# 14. Merge Contacts

กรณี:

``` text
Contact #12
student_code = 68001234
OA งานวิชาการ

Contact #55
student_code = 68001234
OA IT
```

ระบบเสนอ:

``` text
พบรหัสนักศึกษานี้ในระบบแล้ว

68001234

ต้องการเชื่อม LINE Contact นี้
กับ Contact เดิมหรือไม่?

[ยกเลิก] [เชื่อมข้อมูล]
```

เมื่อ Merge:

-   line_identities ย้ายไป Contact หลัก
-   conversations ยังคงอยู่
-   messages ห้ามหาย
-   notes/tags ต้องคงอยู่
-   audit log ต้องบันทึก
-   duplicate contact ถูก mark merged แทน hard delete

------------------------------------------------------------------------

# 15. Conversations

## conversations

``` text
id
line_account_id
line_identity_id
contact_id
status
priority
assigned_user_id nullable
last_message_id nullable
last_message_at nullable
last_inbound_at nullable
last_outbound_at nullable
unread_count
created_at
updated_at
```

Status:

``` text
open
pending
resolved
```

Priority:

``` text
low
normal
high
urgent
```

การ Resolve ไม่ลบประวัติ

เมื่อ Contact เดิมส่งข้อความใหม่ สามารถ reopen conversation ได้ตาม setting

------------------------------------------------------------------------

# 16. Messages

## messages

``` text
id
conversation_id
line_account_id
line_identity_id
direction
sender_type
sender_user_id nullable
line_message_id nullable
webhook_event_id nullable
message_type
text_content nullable
sent_at nullable
received_at nullable
delivery_status
error_code nullable
error_message nullable
raw_payload_json nullable
created_at
updated_at
```

direction:

``` text
inbound
outbound
```

sender_type:

``` text
line_user
staff
system
```

message_type:

``` text
text
image
file
audio
video
sticker
location
flex
unsupported
```

delivery_status:

``` text
received
queued
sent
failed
```

------------------------------------------------------------------------

# 17. Duplicate Prevention

Webhook อาจถูกส่งซ้ำ

ระบบต้อง idempotent

ใช้:

``` text
webhook_event_id
line_message_id
line_account_id
```

แนวทาง:

-   unique webhook_event_id เมื่อมีค่า
-   unique `(line_account_id, line_message_id, direction)` เมื่อมีค่า
-   event ที่เคย process แล้วต้องตอบสำเร็จโดยไม่สร้าง message ซ้ำ

------------------------------------------------------------------------

# 18. Webhook Security

ทุก webhook:

1.  รับ raw request body
2.  อ่าน `x-line-signature`
3.  ใช้ Channel Secret ของ OA นั้น validate signature
4.  Invalid → reject
5.  Valid → process

ห้าม:

-   trust payload โดยไม่ validate
-   expose secret ใน frontend
-   log access token
-   log channel secret

------------------------------------------------------------------------

# 19. Webhook Processing Flow

``` text
POST webhook
    ↓
Resolve OA
    ↓
Verify Signature
    ↓
Check duplicate
    ↓
Resolve/Create LINE Identity
    ↓
Resolve/Create Contact
    ↓
Resolve Conversation
    ↓
Save Message
    ↓
Update unread / last_message
    ↓
Handle Attachment
    ↓
HTTP 200
```

หนึ่ง webhook request อาจมีหลาย events

------------------------------------------------------------------------

# 20. Attachments

รองรับ inbound:

-   image
-   file
-   audio
-   video

## attachments

``` text
id
message_id
original_name nullable
mime_type nullable
size_bytes nullable
storage_disk
storage_path
external_file_id nullable
thumbnail_path nullable
download_status
download_attempts
created_at
updated_at
```

ไม่ควรพึ่ง LINE เป็น long-term file storage

แนะนำ:

``` text
Text → MySQL

Images / Files
→ Google Drive
หรือ
→ S3-compatible Object Storage
```

Shared Hosting เก็บเฉพาะ temporary file หากจำเป็น

------------------------------------------------------------------------

# 21. Live Chat

อาจารย์ตอบจาก Web Dashboard

``` text
Web Dashboard
      ↓
Laravel API
      ↓
LINE Messaging API
      ↓
LINE OA
      ↓
นักศึกษา
```

ข้อความ outbound ทุกข้อความต้องบันทึกลง DB

------------------------------------------------------------------------

# 22. Polling

ไม่ใช้ WebSocket ใน v1

## Active Chat

``` text
2–3 seconds
```

## Inbox

``` text
5 seconds
```

## Browser Hidden

``` text
20–30 seconds
```

เมื่อกลับมา active:

``` text
fetch immediately
```

API ต้องรองรับ delta query เช่น:

``` text
after_id
updated_since
cursor
```

ห้ามโหลด history ทั้งหมดทุก polling cycle

------------------------------------------------------------------------

# 23. Inbox

หน้า Inbox ต้องรองรับ:

-   All OA
-   Filter OA
-   Search
-   Unread
-   Open
-   Pending
-   Resolved
-   Assigned Staff
-   Has Student Code
-   No Student Code
-   Tags

Search:

``` text
LINE Display Name
Student Code
Message Content
```

------------------------------------------------------------------------

# 24. Chat Workspace

Desktop ใช้ 3-column layout:

``` text
Conversation List
       │
       ▼
Chat Timeline
       │
       ▼
Contact Detail
```

Header ต้องแสดง:

-   LINE avatar
-   LINE Display Name
-   Student Code
-   OA
-   Conversation status
-   Assigned Staff

------------------------------------------------------------------------

# 25. Contact Sidebar

ตัวอย่าง:

``` text
LINE Contact

[Avatar]

Bank

รหัสนักศึกษา
68001234

LINE OA
งานวิชาการ

LINE Name
Bank

สถานะ
Open

Tags
[ฝึกงาน] [เอกสาร]

[แก้ไขรหัสนักศึกษา]
```

ถ้าไม่มีรหัส:

``` text
รหัสนักศึกษา
ยังไม่ระบุ

[+ เพิ่มรหัสนักศึกษา]
```

------------------------------------------------------------------------

# 26. Contact Detail

Route:

``` text
/contacts/{id}
```

แสดง:

-   Student Code
-   Note
-   LINE Identities
-   OA ที่เคยติดต่อ
-   Conversations
-   Attachments
-   Internal Notes
-   Activity

ไม่แสดง Level/Class/Department

------------------------------------------------------------------------

# 27. Unified History

ถ้า Contact เดียวมีหลาย LINE identities:

``` text
Student Code 68001234

Timeline

งานวิชาการ
"สอบถามเรื่องฝึกงาน"

งานทะเบียน
"ขอเอกสารรับรอง"

IT Support
"เข้า WiFi ไม่ได้"
```

ทุก message ต้องแสดง OA ต้นทางชัดเจน

------------------------------------------------------------------------

# 28. Internal Notes

เจ้าหน้าที่สามารถเขียน Note ที่ไม่ส่งเข้า LINE

## conversation_notes

``` text
id
conversation_id
user_id
note
created_at
updated_at
```

UI ต้องแยกจาก LINE Message ชัดเจน

------------------------------------------------------------------------

# 29. Tags

## tags

``` text
id
name
created_at
updated_at
```

Pivot:

``` text
conversation_tag
```

ตัวอย่าง:

-   ฝึกงาน
-   เอกสาร
-   การเงิน
-   คะแนน
-   IT
-   เร่งด่วน

------------------------------------------------------------------------

# 30. Assignment

Conversation สามารถ assign ให้เจ้าหน้าที่

``` text
assigned_user_id
```

Filter:

``` text
ของฉัน
ยังไม่มีผู้รับผิดชอบ
เจ้าหน้าที่คนอื่น
```

------------------------------------------------------------------------

# 31. Users & Roles

Roles:

``` text
Super Admin
Admin
Staff
Viewer
```

Permission:

``` text
dashboard.view
inbox.view
message.send
conversation.assign
conversation.resolve
contact.edit
contact.merge
line_account.manage
user.manage
audit.view
settings.manage
```

------------------------------------------------------------------------

# 32. OA Access Permission

เจ้าหน้าที่ไม่จำเป็นต้องเห็นทุก OA

Pivot:

``` text
line_account_user
```

ตัวอย่าง:

``` text
Teacher A
→ งานวิชาการ
→ IT

Teacher B
→ งานทะเบียน

Admin
→ All OA
```

Backend ต้อง enforce permission ทุก endpoint

------------------------------------------------------------------------

# 33. Authentication

แนะนำ Laravel Sanctum

รองรับ:

-   Login
-   Logout
-   Current User
-   Password Reset
-   Session Timeout
-   Login Rate Limit

Future:

-   2FA

------------------------------------------------------------------------

# 34. Security

-   HTTPS
-   LINE webhook signature validation
-   Encrypt LINE credentials
-   CSRF protection
-   XSS protection
-   SQL Injection protection
-   API authorization
-   Rate limiting
-   File validation
-   Signed/private attachment URL
-   Audit log
-   DB backup
-   `.env` ห้าม commit
-   production ห้ามแสดง stack trace

------------------------------------------------------------------------

# 35. Audit Log

## audit_logs

``` text
id
user_id nullable
action
auditable_type
auditable_id nullable
metadata_json nullable
ip_address nullable
user_agent nullable
created_at
```

บันทึก:

-   Login
-   Send Message
-   Send Failure
-   Edit Student Code
-   Merge Contact
-   Assign Conversation
-   Resolve/Reopen
-   OA Configuration
-   User/Permission Change

------------------------------------------------------------------------

# 36. Dashboard

Metrics:

``` text
Unread
Open
Pending
Resolved Today
Unknown Student Code
```

เพิ่มเติม:

-   Message volume per OA
-   Recent conversations
-   Unassigned conversations
-   Failed outbound messages

------------------------------------------------------------------------

# 37. Contact List

Columns:

``` text
LINE Avatar
LINE Name
Student Code
LINE OA Count
Last Contact
Conversation Status
```

Filters:

``` text
Has Student Code
No Student Code
OA
Last Contact
```

------------------------------------------------------------------------

# 38. LINE OA Management

Admin เพิ่ม/แก้ไข OA:

``` text
OA Name
Channel ID
Channel Secret
Channel Access Token
Color
Active
```

แสดง:

``` text
Webhook URL
Webhook Status
Last Event
Connection/Test Result
```

Secret masked by default

------------------------------------------------------------------------

# 39. Settings

## General

``` text
System Name
Timezone
Date Format
```

## Chat

``` text
Auto Reopen Conversation
Polling Interval
Sound Notification
```

## Contact

``` text
Ask Student Code Automatically
Allow Staff Edit Student Code
Allow Contact Merge
```

## Storage

``` text
Local
Google Drive
S3-compatible
```

## Retention

``` text
Message Retention
Raw Webhook Retention
Attachment Retention
```

------------------------------------------------------------------------

# 40. REST API

## Auth

``` text
POST /api/login
POST /api/logout
GET  /api/me
```

## Inbox

``` text
GET   /api/conversations
GET   /api/conversations/{id}
PATCH /api/conversations/{id}
POST  /api/conversations/{id}/assign
POST  /api/conversations/{id}/resolve
POST  /api/conversations/{id}/reopen
```

## Messages

``` text
GET  /api/conversations/{id}/messages
POST /api/conversations/{id}/messages
POST /api/conversations/{id}/read
```

## Contacts

``` text
GET   /api/contacts
GET   /api/contacts/{id}
PATCH /api/contacts/{id}
POST  /api/contacts/{id}/student-code
POST  /api/contacts/{id}/merge
```

## Notes

``` text
POST   /api/conversations/{id}/notes
PATCH  /api/notes/{id}
DELETE /api/notes/{id}
```

## Tags

``` text
GET    /api/tags
POST   /api/conversations/{id}/tags
DELETE /api/conversations/{id}/tags/{tagId}
```

## LINE OA

``` text
GET   /api/line-accounts
POST  /api/line-accounts
GET   /api/line-accounts/{id}
PATCH /api/line-accounts/{id}
POST  /api/line-accounts/{id}/test
```

## Webhook

``` text
POST /api/webhooks/line/{lineAccountUuid}
```

------------------------------------------------------------------------

# 41. Suggested Laravel Structure

``` text
app/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
│
├── Models/
│   ├── User.php
│   ├── LineAccount.php
│   ├── Contact.php
│   ├── LineIdentity.php
│   ├── Conversation.php
│   ├── Message.php
│   ├── Attachment.php
│   ├── Tag.php
│   └── AuditLog.php
│
├── Services/
│   ├── Line/
│   │   ├── LineWebhookService.php
│   │   ├── LineMessagingService.php
│   │   └── LineProfileService.php
│   ├── ContactService.php
│   ├── ConversationService.php
│   └── AttachmentService.php
│
└── Jobs/
    └── ArchiveLineAttachment.php
```

------------------------------------------------------------------------

# 42. Frontend Structure

``` text
src/
├── layouts/
├── views/
│   ├── Dashboard/
│   ├── Inbox/
│   ├── Contacts/
│   ├── LineAccounts/
│   ├── Users/
│   └── Settings/
│
├── components/
│   ├── inbox/
│   ├── chat/
│   ├── contacts/
│   └── common/
│
├── composables/
├── stores/
├── services/
└── router/
```

Pinia สามารถใช้สำหรับ frontend state management

------------------------------------------------------------------------

# 43. Database Indexes

อย่างน้อย:

``` text
line_identities(line_account_id, line_user_id)

contacts(student_code)

conversations(
    line_account_id,
    status,
    assigned_user_id,
    last_message_at
)

messages(
    conversation_id,
    id,
    created_at
)

messages(line_message_id)

messages(webhook_event_id)
```

------------------------------------------------------------------------

# 44. Search Strategy

v1:

MySQL indexes + LIKE / Full Text หาก Hosting รองรับ

ค้นหา:

``` text
student_code
display_name
message text
```

ถ้าข้อมูลโตมากค่อยย้ายไป:

``` text
Meilisearch
Elasticsearch
```

ไม่จำเป็นใน MVP

------------------------------------------------------------------------

# 45. Performance

-   Cursor pagination สำหรับ messages
-   Pagination สำหรับ Inbox
-   Polling ดึงเฉพาะข้อมูลใหม่
-   Lazy load image
-   Thumbnail
-   Cache OA configuration
-   หลีกเลี่ยง N+1 Query
-   ใช้ DB Index
-   Webhook response ต้องเร็ว
-   ไม่โหลด raw payload ไป frontend

------------------------------------------------------------------------

# 46. Shared Hosting Cron

ตัวอย่าง:

``` bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler ใช้สำหรับ:

-   retry attachment
-   cleanup temp file
-   backup task
-   cleanup raw webhook
-   maintenance jobs

------------------------------------------------------------------------

# 47. Message Sending Rules

Dashboard ต้องไม่ assume ว่าสามารถส่งข้อความได้ทุกกรณี

Laravel ต้อง:

1.  ตรวจ recipient
2.  เรียก LINE Messaging API
3.  จับ API response
4.  บันทึก sent/failed
5.  แสดง error ให้ Staff

Reply Token ไม่ควรเป็นกลไกหลักของ Live Chat Dashboard
เพราะเจ้าหน้าที่อาจตอบภายหลัง

------------------------------------------------------------------------

# 48. Error Handling

ตัวอย่าง:

``` text
LINE API Error
Network Error
Permission Denied
Attachment Download Failed
Invalid Webhook Signature
Invalid Contact Merge
Duplicate Student Code
Session Expired
```

Frontend ใช้ Snackbar/Dialog ตามความเหมาะสม

------------------------------------------------------------------------

# 49. Backup

แนะนำ:

``` text
MySQL
→ Daily Backup

Attachments
→ External Storage

Configuration
→ Encrypted Backup
```

ต้องมีคู่มือ Restore

------------------------------------------------------------------------

# 50. MVP Acceptance Criteria

ระบบถือว่า MVP พร้อมเมื่อ:

1.  เพิ่ม LINE OA ได้อย่างน้อย 2 บัญชี
2.  แต่ละ OA รับ webhook ได้
3.  Webhook ตรวจ signature
4.  Contact ใหม่ถูกสร้างอัตโนมัติ
5.  Contact ไม่มี student_code ก็สนทนาได้
6.  Staff เพิ่ม student_code ภายหลังได้
7.  student_code ไม่ต้องมีข้อมูลระดับ/ห้อง/สาขา
8.  Contact หลาย OA สามารถ merge ได้
9.  ประวัติเดิมไม่หายหลัง merge
10. Inbox รวมหลาย OA ได้
11. Filter ตาม OA ได้
12. Staff ตอบ text ผ่าน Web Dashboard ได้
13. หน้า Chat update ผ่าน polling
14. ไม่มี duplicate message เมื่อ webhook retry
15. Search ด้วย student_code ได้
16. Search ด้วย LINE name ได้
17. Search message history ได้
18. รองรับ Internal Note
19. รองรับ Tag
20. รองรับ Assignment
21. รองรับ Open/Pending/Resolved
22. Staff เห็นเฉพาะ OA ที่มีสิทธิ์
23. Attachment ถูก archive
24. ทำงานบน Shared Hosting โดยไม่ใช้ WebSocket
25. มี Audit Log

------------------------------------------------------------------------

# 51. Development Phases

## Phase 1 --- Core

-   Laravel
-   Vue/Vuetify
-   Auth
-   RBAC
-   LINE OA Management
-   Webhook
-   Contact
-   Conversation
-   Text Message

## Phase 2 --- Live Chat

-   Inbox
-   Polling
-   Send Message
-   Unread
-   Open/Pending/Resolved
-   Assignment

## Phase 3 --- Contact Identification

-   Student Code
-   Search Student Code
-   Unknown Contact
-   Merge Contact
-   Unified History

## Phase 4 --- Productivity

-   Tags
-   Internal Notes
-   Search
-   Filters

## Phase 5 --- Attachments

-   Image
-   File
-   Audio
-   Video archive
-   External Storage

## Phase 6 --- Operations

-   Dashboard
-   Audit
-   Backup
-   Reports
-   Optimization

## Future Scale

หากย้ายจาก Shared Hosting ไป VPS:

``` text
Polling
→ Laravel Reverb / WebSocket

Database Queue
→ Redis Queue

Cron Worker
→ Supervisor

Storage
→ Object Storage
```

Database model หลักต้องไม่ต้องรื้อใหม่

------------------------------------------------------------------------

# 52. Final Product Concept

ระบบนี้ไม่ใช่ Student Information System

ระบบนี้คือ:

**Multi LINE OA Communication Archive & Live Chat**

หน้าที่หลักคือ:

``` text
รู้ว่า LINE คนไหนเคยติดต่ออะไร
        +
ถ้ามีรหัสนักศึกษา ก็ใช้รหัสนั้นรวมและค้นประวัติ
        +
เก็บประวัติแชทระยะยาว
        +
ตอบ LINE จากระบบกลาง
```
