<?php

namespace App\Http\Controllers;

use App\Models\LineAccount;
use App\Services\AuditService;
use App\Services\Line\LineMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LineAccountController extends Controller
{
    public function __construct(
        private LineMessagingService $messaging,
        private AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $ids = $request->user()->accessibleLineAccountIds();

        $accounts = LineAccount::query()
            ->whereIn('id', $ids ?: [-1])
            ->orderBy('name')
            ->get()
            ->map(fn (LineAccount $a) => $this->present($a));

        return response()->json(['data' => $accounts]);
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:line_accounts,slug'],
            'channel_id' => ['nullable', 'string', 'max:255'],
            'channel_secret' => ['required', 'string'],
            'channel_access_token' => ['required', 'string'],
            'account_color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ]);

        $account = LineAccount::query()->create([
            ...$data,
            'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->audit->log('line_account.create', $account);

        return response()->json(['data' => $this->present($account)], 201);
    }

    public function show(Request $request, LineAccount $lineAccount)
    {
        $this->authorizeAccess($request, $lineAccount);

        return response()->json(['data' => $this->present($lineAccount)]);
    }

    public function update(Request $request, LineAccount $lineAccount)
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('line_accounts', 'slug')->ignore($lineAccount->id)],
            'channel_id' => ['nullable', 'string', 'max:255'],
            'channel_secret' => ['nullable', 'string'],
            'channel_access_token' => ['nullable', 'string'],
            'account_color' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ]);

        if (empty($data['channel_secret'])) {
            unset($data['channel_secret']);
        }
        if (empty($data['channel_access_token'])) {
            unset($data['channel_access_token']);
        }

        $lineAccount->update($data);
        $this->audit->log('line_account.update', $lineAccount);

        return response()->json(['data' => $this->present($lineAccount->fresh())]);
    }

    public function test(Request $request, LineAccount $lineAccount)
    {
        $this->authorizeManage($request);

        $result = $this->messaging->getBotInfo($lineAccount);

        if ($result['ok'] && ! empty($result['body']['userId'])) {
            $lineAccount->update(['bot_user_id' => $result['body']['userId']]);
        }

        return response()->json([
            'ok' => $result['ok'],
            'status' => $result['status'],
            'bot' => $result['body'],
            'data' => $this->present($lineAccount->fresh()),
        ], $result['ok'] ? 200 : 422);
    }

    private function present(LineAccount $account): array
    {
        return [
            'id' => $account->id,
            'uuid' => $account->uuid,
            'name' => $account->name,
            'slug' => $account->slug,
            'channel_id' => $account->channel_id,
            'channel_secret_masked' => $account->maskedSecret(),
            'bot_user_id' => $account->bot_user_id,
            'account_color' => $account->account_color,
            'is_active' => $account->is_active,
            'last_webhook_at' => $account->last_webhook_at,
            'webhook_url' => $account->webhookUrl(),
            'created_at' => $account->created_at,
            'updated_at' => $account->updated_at,
        ];
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->can('line_account.manage'), 403);
    }

    private function authorizeAccess(Request $request, LineAccount $account): void
    {
        abort_unless(in_array($account->id, $request->user()->accessibleLineAccountIds(), true), 403);
    }
}
