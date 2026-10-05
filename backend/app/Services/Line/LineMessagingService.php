<?php

namespace App\Services\Line;

use App\Models\LineAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineMessagingService
{
    public function pushText(LineAccount $account, string $lineUserId, string $text): array
    {
        return $this->push($account, $lineUserId, [
            ['type' => 'text', 'text' => $text],
        ]);
    }

    public function pushImage(LineAccount $account, string $lineUserId, string $originalUrl, ?string $previewUrl = null): array
    {
        return $this->push($account, $lineUserId, [[
            'type' => 'image',
            'originalContentUrl' => $originalUrl,
            'previewImageUrl' => $previewUrl ?: $originalUrl,
        ]]);
    }

    public function pushVideo(LineAccount $account, string $lineUserId, string $originalUrl, string $previewUrl): array
    {
        return $this->push($account, $lineUserId, [[
            'type' => 'video',
            'originalContentUrl' => $originalUrl,
            'previewImageUrl' => $previewUrl,
        ]]);
    }

    public function push(LineAccount $account, string $lineUserId, array $messages): array
    {
        $response = Http::withToken($account->channel_access_token)
            ->acceptJson()
            ->post('https://api.line.me/v2/bot/message/push', [
                'to' => $lineUserId,
                'messages' => $messages,
            ]);

        if ($response->failed()) {
            Log::warning('LINE push failed', [
                'line_account_id' => $account->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json(),
        ];
    }

    public function getBotInfo(LineAccount $account): array
    {
        $response = Http::withToken($account->channel_access_token)
            ->acceptJson()
            ->get('https://api.line.me/v2/bot/info');

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json(),
        ];
    }
}
