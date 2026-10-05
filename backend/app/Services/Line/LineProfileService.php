<?php

namespace App\Services\Line;

use App\Models\LineAccount;
use Illuminate\Support\Facades\Http;

class LineProfileService
{
    public function getProfile(LineAccount $account, string $lineUserId): ?array
    {
        $response = Http::withToken($account->channel_access_token)
            ->acceptJson()
            ->get("https://api.line.me/v2/bot/profile/{$lineUserId}");

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }
}
