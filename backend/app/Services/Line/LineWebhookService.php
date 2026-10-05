<?php

namespace App\Services\Line;

use App\Jobs\ProcessLineWebhookEvent;
use App\Models\LineAccount;
use App\Models\WebhookEvent;
use App\Services\AuditService;
use Illuminate\Support\Facades\Cache;

class LineWebhookService
{
    public function __construct(
        private AuditService $audit,
    ) {}

    public function resolveAccount(string $uuid): ?LineAccount
    {
        // Cache only the id — never serialize Eloquent models (database cache can return Incomplete_Class).
        $id = Cache::remember("line_account.id.{$uuid}", 300, function () use ($uuid) {
            return LineAccount::query()
                ->where('uuid', $uuid)
                ->where('is_active', true)
                ->value('id');
        });

        if (! $id) {
            return null;
        }

        return LineAccount::query()->find($id);
    }

    public function verifySignature(LineAccount $account, string $rawBody, ?string $signature): bool
    {
        if (! $signature) {
            return false;
        }

        $hash = base64_encode(hash_hmac('sha256', $rawBody, $account->channel_secret, true));

        return hash_equals($hash, $signature);
    }

    public function ingest(LineAccount $account, array $payload): void
    {
        $account->forceFill(['last_webhook_at' => now()])->save();
        Cache::forget("line_account.id.{$account->uuid}");
        Cache::forget("line_account.uuid.{$account->uuid}");

        $events = $payload['events'] ?? [];

        if ($events === []) {
            WebhookEvent::query()->create([
                'line_account_id' => $account->id,
                'event_type' => 'empty',
                'status' => 'processed',
                'payload' => $payload,
                'processed_at' => now(),
            ]);

            return;
        }

        foreach ($events as $event) {
            $eventId = $event['webhookEventId'] ?? $event['message']['id'] ?? null;

            if ($eventId) {
                $exists = WebhookEvent::query()->where('event_id', $eventId)->exists();
                if ($exists) {
                    continue;
                }
            }

            $webhookEvent = WebhookEvent::query()->create([
                'line_account_id' => $account->id,
                'event_id' => $eventId,
                'event_type' => $event['type'] ?? null,
                'status' => 'pending',
                'payload' => $event,
            ]);

            ProcessLineWebhookEvent::dispatch($webhookEvent->id);
        }
    }

    public function rejectInvalidSignature(LineAccount $account): void
    {
        $this->audit->log('webhook.invalid_signature', $account, [
            'line_account_id' => $account->id,
        ]);
    }
}
