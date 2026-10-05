<?php

namespace App\Http\Controllers;

use App\Services\Line\LineWebhookService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(private LineWebhookService $webhooks) {}

    public function handle(Request $request, string $lineAccountUuid)
    {
        $account = $this->webhooks->resolveAccount($lineAccountUuid);

        if (! $account) {
            return response()->json(['message' => 'Unknown LINE account'], 404);
        }

        $raw = $request->getContent();
        $signature = $request->header('x-line-signature');

        if (! $this->webhooks->verifySignature($account, $raw, $signature)) {
            $this->webhooks->rejectInvalidSignature($account);

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($raw, true) ?? [];
        $this->webhooks->ingest($account, $payload);

        return response()->json(['ok' => true]);
    }
}
