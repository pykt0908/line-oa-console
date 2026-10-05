<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\LineAccount;
use App\Models\LineIdentity;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Services\Line\LineMessagingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class ConversationService
{
    public function __construct(
        private LineMessagingService $messaging,
        private AuditService $audit,
        private AttachmentStorage $storage,
    ) {}

    public function findOrCreate(LineAccount $account, LineIdentity $identity): Conversation
    {
        $conversation = Conversation::query()
            ->where('line_account_id', $account->id)
            ->where('line_identity_id', $identity->id)
            ->whereIn('status', ['open', 'pending'])
            ->latest('id')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $resolved = Conversation::query()
            ->where('line_account_id', $account->id)
            ->where('line_identity_id', $identity->id)
            ->where('status', 'resolved')
            ->latest('id')
            ->first();

        if ($resolved && Setting::getBool('chat.auto_reopen', true)) {
            $resolved->update(['status' => 'open']);

            return $resolved;
        }

        return Conversation::query()->create([
            'line_account_id' => $account->id,
            'line_identity_id' => $identity->id,
            'contact_id' => $identity->contact_id,
            'status' => 'open',
            'priority' => 'normal',
            'unread_count' => 0,
        ]);
    }

    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $accountIds = $user->accessibleLineAccountIds();

        $query = Conversation::query()
            ->with([
                'lineAccount:id,name,account_color,slug',
                'lineIdentity:id,display_name,picture_url,line_user_id',
                'contact:id,student_code,contact_type',
                'assignee:id,name,display_name',
                'lastMessage:id,text_content,direction,message_type,created_at,delivery_status',
                'tags:id,name,color',
            ])
            ->whereIn('line_account_id', $accountIds ?: [-1])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        $this->applyFilters($query, $filters);

        return $query->paginate(min((int) ($filters['per_page'] ?? 30), 100));
    }

    public function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['line_account_id'])) {
            $query->where('line_account_id', $filters['line_account_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['unread'])) {
            $query->where('unread_count', '>', 0);
        }

        if (($filters['assigned'] ?? null) === 'me' && auth()->id()) {
            $query->where('assigned_user_id', auth()->id());
        } elseif (($filters['assigned'] ?? null) === 'unassigned') {
            $query->whereNull('assigned_user_id');
        } elseif (($filters['assigned'] ?? null) === 'others' && auth()->id()) {
            $query->whereNotNull('assigned_user_id')->where('assigned_user_id', '!=', auth()->id());
        }

        if (($filters['has_student_code'] ?? null) === '1') {
            $query->whereHas('contact', fn (Builder $q) => $q->whereNotNull('student_code'));
        } elseif (($filters['has_student_code'] ?? null) === '0') {
            $query->whereHas('contact', fn (Builder $q) => $q->whereNull('student_code'));
        }

        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', fn (Builder $q) => $q->where('tags.id', $filters['tag_id']));
        }

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function (Builder $builder) use ($q) {
                $builder->whereHas('lineIdentity', fn (Builder $i) => $i->where('display_name', 'like', "%{$q}%"))
                    ->orWhereHas('contact', fn (Builder $c) => $c->where('student_code', 'like', "%{$q}%"))
                    ->orWhereHas('messages', function (Builder $m) use ($q) {
                        $m->where('text_content', 'like', "%{$q}%");
                    });
            });
        }

        if (! empty($filters['updated_since'])) {
            $query->where('updated_at', '>', $filters['updated_since']);
        }
    }

    public function storeInboundMessage(Conversation $conversation, array $data): Message
    {
        return DB::transaction(function () use ($conversation, $data) {
            if (! empty($data['line_message_id'])) {
                $existing = Message::query()
                    ->where('line_account_id', $conversation->line_account_id)
                    ->where('line_message_id', $data['line_message_id'])
                    ->where('direction', 'inbound')
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            if (! empty($data['webhook_event_id'])) {
                $existing = Message::query()->where('webhook_event_id', $data['webhook_event_id'])->first();
                if ($existing) {
                    return $existing;
                }
            }

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'line_account_id' => $conversation->line_account_id,
                'line_identity_id' => $conversation->line_identity_id,
                'direction' => 'inbound',
                'sender_type' => 'line_user',
                'line_message_id' => $data['line_message_id'] ?? null,
                'webhook_event_id' => $data['webhook_event_id'] ?? null,
                'message_type' => $data['message_type'] ?? 'text',
                'text_content' => $data['text_content'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'delivery_status' => 'received',
                'raw_payload_json' => $data['raw_payload_json'] ?? null,
            ]);

            $conversation->update([
                'last_message_id' => $message->id,
                'last_message_at' => $message->received_at ?? now(),
                'last_inbound_at' => $message->received_at ?? now(),
                'unread_count' => $conversation->unread_count + 1,
                'status' => $conversation->status === 'resolved' && Setting::getBool('chat.auto_reopen', true)
                    ? 'open'
                    : $conversation->status,
            ]);

            return $message;
        });
    }

    public function sendOutboundText(Conversation $conversation, User $user, string $text): Message
    {
        $identity = $conversation->lineIdentity;
        $account = $conversation->lineAccount;

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'line_account_id' => $conversation->line_account_id,
            'line_identity_id' => $conversation->line_identity_id,
            'direction' => 'outbound',
            'sender_type' => 'staff',
            'sender_user_id' => $user->id,
            'message_type' => 'text',
            'text_content' => $text,
            'delivery_status' => 'queued',
            'sent_at' => now(),
        ]);

        $result = $this->messaging->pushText($account, $identity->line_user_id, $text);

        if ($result['ok']) {
            $message->update(['delivery_status' => 'sent']);
            $this->audit->log('message.send', $message, [
                'conversation_id' => $conversation->id,
            ], $user->id);
        } else {
            $body = $result['body'] ?? [];
            $message->update([
                'delivery_status' => 'failed',
                'error_code' => (string) ($body['message'] ?? $result['status'] ?? 'error'),
                'error_message' => is_array($body) ? json_encode($body) : (string) $body,
            ]);
            $this->audit->log('message.send_failure', $message, [
                'conversation_id' => $conversation->id,
                'error' => $message->error_message,
            ], $user->id);
        }

        $conversation->update([
            'last_message_id' => $message->id,
            'last_message_at' => now(),
            'last_outbound_at' => now(),
            'assigned_user_id' => $conversation->assigned_user_id ?? $user->id,
        ]);

        return $message->fresh(['sender:id,name,display_name', 'attachments']);
    }

    public function sendOutboundMedia(Conversation $conversation, User $user, UploadedFile $file, ?string $caption = null): Message
    {
        $identity = $conversation->lineIdentity;
        $account = $conversation->lineAccount;
        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $originalName = $file->getClientOriginalName() ?: ('file-'.Str::uuid());
        $messageType = match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            default => 'file',
        };

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            throw new RuntimeException('อ่านไฟล์ไม่สำเร็จ');
        }

        $text = match ($messageType) {
            'image' => $caption ?: '[รูปภาพ]',
            'video' => $caption ?: '[วิดีโอ]',
            default => $caption ?: ('[ไฟล์] '.$originalName),
        };

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'line_account_id' => $conversation->line_account_id,
            'line_identity_id' => $conversation->line_identity_id,
            'direction' => 'outbound',
            'sender_type' => 'staff',
            'sender_user_id' => $user->id,
            'message_type' => $messageType,
            'text_content' => $text,
            'delivery_status' => 'queued',
            'sent_at' => now(),
        ]);

        $storedPath = $this->storage->put(
            'attachments/'.date('Y/m/').Str::uuid().'_'.$originalName,
            $bytes,
            $mime
        );

        $attachment = Attachment::query()->create([
            'message_id' => $message->id,
            'original_name' => $originalName,
            'mime_type' => $mime,
            'size_bytes' => strlen($bytes),
            'storage_disk' => $this->storage->diskName(),
            'storage_path' => $storedPath,
            'download_status' => 'completed',
            'download_attempts' => 0,
        ]);

        $contentUrl = $this->publicAttachmentUrl($attachment);

        if (in_array($messageType, ['image', 'video'], true) && ! str_starts_with($contentUrl, 'https://')) {
            $message->update([
                'delivery_status' => 'failed',
                'error_code' => 'https_required',
                'error_message' => 'LINE ต้องการ URL แบบ HTTPS — ตั้ง LINE_PUBLIC_BASE_URL เป็น URL tunnel/โดเมนสาธารณะ',
            ]);
            throw new RuntimeException($message->error_message);
        }

        $result = match ($messageType) {
            'image' => $this->messaging->pushImage($account, $identity->line_user_id, $contentUrl, $contentUrl),
            'video' => $this->messaging->pushVideo(
                $account,
                $identity->line_user_id,
                $contentUrl,
                $this->videoPreviewUrl()
            ),
            default => $this->messaging->pushText(
                $account,
                $identity->line_user_id,
                trim(($caption ? $caption."\n" : '').'ไฟล์: '.$originalName."\nลิงก์ดาวน์โหลด: ".$contentUrl)
            ),
        };

        if ($result['ok'] && $caption && in_array($messageType, ['image', 'video'], true)) {
            $this->messaging->pushText($account, $identity->line_user_id, $caption);
        }

        if ($result['ok']) {
            $message->update(['delivery_status' => 'sent']);
            $this->audit->log('message.send_media', $message, [
                'conversation_id' => $conversation->id,
                'message_type' => $messageType,
                'attachment_id' => $attachment->id,
            ], $user->id);
        } else {
            $body = $result['body'] ?? [];
            $details = collect($body['details'] ?? [])->pluck('message')->filter()->implode('; ');
            $errorMessage = $details !== ''
                ? $details
                : (string) ($body['message'] ?? 'ส่งไป LINE ไม่สำเร็จ');

            if (str_contains($errorMessage, 'HTTPS')) {
                $errorMessage = 'LINE ต้องการ URL แบบ HTTPS — ตรวจ LINE_PUBLIC_BASE_URL / tunnel ว่าชี้มาที่ backend และรัน php artisan config:clear';
            }

            $message->update([
                'delivery_status' => 'failed',
                'error_code' => (string) ($body['message'] ?? $result['status'] ?? 'error'),
                'error_message' => is_array($body) ? json_encode($body) : (string) $body,
            ]);
            $this->audit->log('message.send_failure', $message, [
                'conversation_id' => $conversation->id,
                'error' => $message->error_message,
            ], $user->id);

            throw new RuntimeException($errorMessage);
        }

        $conversation->update([
            'last_message_id' => $message->id,
            'last_message_at' => now(),
            'last_outbound_at' => now(),
            'assigned_user_id' => $conversation->assigned_user_id ?? $user->id,
        ]);

        return Message::presentMany([
            $message->fresh(['sender:id,name,display_name', 'attachments']),
        ])->first();
    }

    private function linePublicBaseUrl(): string
    {
        $base = trim((string) config('services.line.public_base_url', config('app.url')));

        return rtrim($base, '/');
    }

    private function publicAttachmentUrl(Attachment $attachment): string
    {
        $path = URL::temporarySignedRoute(
            'attachments.download',
            now()->addDays(2),
            ['attachment' => $attachment->id],
            absolute: false,
        );

        return $this->linePublicBaseUrl().$path;
    }

    private function videoPreviewUrl(): string
    {
        return $this->linePublicBaseUrl().'/line-video-preview.jpg';
    }

    public function markRead(Conversation $conversation): Conversation
    {
        $conversation->update(['unread_count' => 0]);

        return $conversation;
    }
}
