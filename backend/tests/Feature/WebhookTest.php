<?php

namespace Tests\Feature;

use App\Jobs\ProcessLineWebhookEvent;
use App\Models\LineAccount;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private function account(): LineAccount
    {
        return LineAccount::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'งานวิชาการ',
            'slug' => 'academic-test-'.Str::lower(Str::random(4)),
            'channel_secret' => 'test-channel-secret',
            'channel_access_token' => 'test-access-token',
            'is_active' => true,
        ]);
    }

    private function signed(string $body, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    public function test_rejects_invalid_signature(): void
    {
        $account = $this->account();
        $body = json_encode(['events' => []]);

        $this->call('POST', '/api/webhooks/line/'.$account->uuid, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => 'invalid',
        ], $body)->assertUnauthorized();
    }

    public function test_accepts_valid_signature_and_is_idempotent(): void
    {
        Queue::fake();

        $account = $this->account();
        $payload = [
            'destination' => 'Uxxxx',
            'events' => [[
                'type' => 'message',
                'webhookEventId' => 'evt-1',
                'timestamp' => now()->getTimestampMs(),
                'source' => ['type' => 'user', 'userId' => 'Uuser1'],
                'message' => ['type' => 'text', 'id' => 'mid-1', 'text' => 'สวัสดี'],
            ]],
        ];
        $body = json_encode($payload);
        $sig = $this->signed($body, 'test-channel-secret');

        for ($i = 0; $i < 3; $i++) {
            $this->call('POST', '/api/webhooks/line/'.$account->uuid, [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_LINE_SIGNATURE' => $sig,
            ], $body)->assertOk();
        }

        Queue::assertPushed(ProcessLineWebhookEvent::class, 1);
    }

    public function test_processes_message_without_duplicate(): void
    {
        Http::fake([
            'https://api.line.me/v2/bot/profile/*' => Http::response([
                'displayName' => 'Bank',
                'userId' => 'Ubank',
                'pictureUrl' => null,
            ], 200),
        ]);

        $account = $this->account();

        $payload = [
            'events' => [[
                'type' => 'message',
                'webhookEventId' => 'evt-dup-1',
                'timestamp' => now()->getTimestampMs(),
                'source' => ['type' => 'user', 'userId' => 'Ubank'],
                'message' => ['type' => 'text', 'id' => 'mid-dup-1', 'text' => 'สอบถามเรื่องฝึกงาน'],
            ]],
        ];
        $body = json_encode($payload);
        $sig = $this->signed($body, 'test-channel-secret');

        $this->call('POST', '/api/webhooks/line/'.$account->uuid, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => $sig,
        ], $body)->assertOk();

        $this->artisan('queue:work', ['--once' => true]);

        $this->call('POST', '/api/webhooks/line/'.$account->uuid, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => $sig,
        ], $body)->assertOk();

        $this->assertSame(1, Message::query()->where('line_message_id', 'mid-dup-1')->count());
    }
}
