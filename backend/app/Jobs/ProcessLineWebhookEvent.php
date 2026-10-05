<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\Setting;
use App\Models\WebhookEvent;
use App\Services\AttachmentService;
use App\Services\ContactService;
use App\Services\ConversationService;
use App\Services\Line\LineMessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessLineWebhookEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $webhookEventId) {}

    public function handle(
        ContactService $contacts,
        ConversationService $conversations,
        AttachmentService $attachments,
        LineMessagingService $messaging,
    ): void {
        $event = WebhookEvent::query()->with('lineAccount')->find($this->webhookEventId);

        if (! $event || $event->status === 'processed') {
            return;
        }

        try {
            $payload = $event->payload;
            $type = $payload['type'] ?? null;
            $account = $event->lineAccount;

            if ($type === 'message') {
                $source = $payload['source'] ?? [];
                $lineUserId = $source['userId'] ?? null;
                $message = $payload['message'] ?? [];

                if ($lineUserId && $message) {
                    $identity = $contacts->findOrCreateIdentity($account, $lineUserId);
                    $conversation = $conversations->findOrCreate($account, $identity);

                    $messageType = $message['type'] ?? 'unsupported';
                    $text = match ($messageType) {
                        'text' => $message['text'] ?? '',
                        'sticker' => '[สติกเกอร์]',
                        'image' => '[รูปภาพ]',
                        'video' => '[วิดีโอ]',
                        'audio' => '[เสียง]',
                        'file' => '[ไฟล์] '.($message['fileName'] ?? ''),
                        'location' => '[ตำแหน่ง]',
                        default => '[ข้อความที่ไม่รองรับ]',
                    };

                    $stored = $conversations->storeInboundMessage($conversation, [
                        'line_message_id' => $message['id'] ?? null,
                        'webhook_event_id' => $payload['webhookEventId'] ?? $event->event_id,
                        'message_type' => in_array($messageType, ['text', 'image', 'file', 'audio', 'video', 'sticker', 'location', 'flex'], true)
                            ? $messageType
                            : 'unsupported',
                        'text_content' => $text,
                        'received_at' => isset($payload['timestamp'])
                            ? now()->createFromTimestampMs($payload['timestamp'])
                            : now(),
                        'raw_payload_json' => $payload,
                    ]);

                    if (in_array($messageType, ['image', 'file', 'audio', 'video'], true) && ! empty($message['id'])) {
                        $attachment = $attachments->createPending(
                            $stored,
                            $message['id'],
                            $messageType,
                            $message['fileName'] ?? null,
                        );
                        ArchiveLineAttachment::dispatch($attachment->id);
                    }

                    if (
                        Setting::getBool('contact.ask_student_code', false)
                        && ! $identity->contact->hasStudentCode()
                        && Message::query()->where('conversation_id', $conversation->id)->where('direction', 'inbound')->count() === 1
                    ) {
                        $messaging->pushText($account, $lineUserId,
                            "เพื่อความสะดวกในการค้นหาประวัติการติดต่อ\nกรุณาระบุรหัสนักศึกษาของคุณ");
                    }
                }
            } elseif ($type === 'follow') {
                $lineUserId = $payload['source']['userId'] ?? null;
                if ($lineUserId) {
                    $contacts->findOrCreateIdentity($account, $lineUserId);
                }
            } elseif ($type === 'unfollow') {
                $lineUserId = $payload['source']['userId'] ?? null;
                if ($lineUserId) {
                    $identity = $account->identities()->where('line_user_id', $lineUserId)->first();
                    $identity?->update(['blocked_at' => now()]);
                }
            }

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Webhook processing failed', [
                'webhook_event_id' => $this->webhookEventId,
                'error' => $e->getMessage(),
            ]);
            $event->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
