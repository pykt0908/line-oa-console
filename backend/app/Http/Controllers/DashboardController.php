<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('dashboard.view'), 403);

        $accountIds = $request->user()->accessibleLineAccountIds() ?: [-1];

        $base = Conversation::query()->whereIn('line_account_id', $accountIds);

        $metrics = [
            'unread' => (clone $base)->sum('unread_count'),
            'open' => (clone $base)->where('status', 'open')->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'resolved_today' => (clone $base)->where('status', 'resolved')->whereDate('updated_at', today())->count(),
            'unknown_student_code' => Contact::query()
                ->whereNull('merged_into_contact_id')
                ->whereNull('student_code')
                ->whereHas('identities', fn ($q) => $q->whereIn('line_account_id', $accountIds))
                ->count(),
            'unassigned' => (clone $base)->whereNull('assigned_user_id')->whereIn('status', ['open', 'pending'])->count(),
            'failed_outbound' => Message::query()
                ->whereIn('line_account_id', $accountIds)
                ->where('direction', 'outbound')
                ->where('delivery_status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];

        $volumePerOa = Message::query()
            ->select('line_account_id', DB::raw('count(*) as total'))
            ->whereIn('line_account_id', $accountIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('line_account_id')
            ->get()
            ->map(function ($row) {
                $account = \App\Models\LineAccount::query()
                    ->select('id', 'name', 'account_color')
                    ->find($row->line_account_id);

                return [
                    'line_account_id' => $row->line_account_id,
                    'total' => $row->total,
                    'line_account' => $account,
                ];
            });

        $recent = (clone $base)
            ->with([
                'lineAccount:id,name,account_color',
                'lineIdentity:id,display_name,picture_url',
                'contact:id,student_code',
                'lastMessage:id,text_content,created_at',
            ])
            ->orderByDesc('last_message_at')
            ->limit(10)
            ->get();

        return response()->json([
            'metrics' => $metrics,
            'volume_per_oa' => $volumePerOa,
            'recent_conversations' => $recent,
        ]);
    }
}
