<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\ConversationNote;
use App\Models\Message;
use App\Models\Tag;
use App\Services\AttachmentStorage;
use App\Services\AuditService;
use App\Services\ConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ConversationController extends Controller
{
    public function __construct(
        private ConversationService $conversations,
        private AuditService $audit,
        private AttachmentStorage $storage,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('inbox.view'), 403);

        $paginator = $this->conversations->listForUser($request->user(), $request->all());

        return response()->json($paginator);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->load([
            'lineAccount:id,name,account_color,slug,uuid',
            'lineIdentity',
            'contact.identities.lineAccount:id,name,account_color',
            'assignee:id,name,display_name',
            'tags',
        ]);

        return response()->json(['data' => $conversation]);
    }

    public function update(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate([
            'status' => ['sometimes', 'in:open,pending,resolved'],
            'priority' => ['sometimes', 'in:low,normal,high,urgent'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $conversation->update($data);

        return response()->json(['data' => $conversation->fresh(['assignee:id,name,display_name', 'tags'])]);
    }

    public function assign(Request $request, Conversation $conversation)
    {
        abort_unless($request->user()->can('conversation.assign'), 403);
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate([
            'assigned_user_id' => ['nullable', 'exists:users,id'],
        ]);

        $conversation->update(['assigned_user_id' => $data['assigned_user_id'] ?? null]);
        $this->audit->log('conversation.assign', $conversation, $data);

        return response()->json(['data' => $conversation->fresh('assignee:id,name,display_name')]);
    }

    public function resolve(Request $request, Conversation $conversation)
    {
        abort_unless($request->user()->can('conversation.resolve'), 403);
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['status' => 'resolved']);
        $this->audit->log('conversation.resolve', $conversation);

        return response()->json(['data' => $conversation]);
    }

    public function reopen(Request $request, Conversation $conversation)
    {
        abort_unless($request->user()->can('conversation.resolve'), 403);
        $this->authorizeConversation($request, $conversation);

        $conversation->update(['status' => 'open']);
        $this->audit->log('conversation.reopen', $conversation);

        return response()->json(['data' => $conversation]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $query = Message::query()
            ->with(['sender:id,name,display_name', 'attachments', 'lineAccount:id,name,account_color'])
            ->where('conversation_id', $conversation->id)
            ->orderBy('id');

        if ($request->filled('after_id')) {
            $query->where('id', '>', (int) $request->after_id);
        }

        if ($request->filled('before_id')) {
            $query->where('id', '<', (int) $request->before_id)->orderByDesc('id');
            $items = $query->limit(min((int) $request->get('limit', 50), 100))->get()->sortBy('id')->values();

            return response()->json(['data' => Message::presentMany($items)->values()]);
        }

        $items = $query->limit(min((int) $request->get('limit', 50), 100))->get();

        return response()->json(['data' => Message::presentMany($items)->values()]);
    }

    public function sendMessage(Request $request, Conversation $conversation)
    {
        abort_unless($request->user()->can('message.send'), 403);
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $message = $this->conversations->sendOutboundText($conversation, $request->user(), $data['text']);

        return response()->json(['data' => $message], 201);
    }

    public function sendMedia(Request $request, Conversation $conversation)
    {
        abort_unless($request->user()->can('message.send'), 403);
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:51200'], // 50MB
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $file = $data['file'];
        $mime = (string) ($file->getMimeType() ?: '');
        if (
            ! str_starts_with($mime, 'image/')
            && ! str_starts_with($mime, 'video/')
            && ! str_starts_with($mime, 'application/')
            && ! str_starts_with($mime, 'text/')
        ) {
            return response()->json(['message' => 'รองรับเฉพาะรูปภาพ วิดีโอ หรือไฟล์เอกสาร'], 422);
        }

        try {
            $message = $this->conversations->sendOutboundMedia(
                $conversation,
                $request->user(),
                $file,
                $data['caption'] ?? null,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'ส่งไฟล์ไม่สำเร็จ'], 500);
        }

        return response()->json(['data' => $message], 201);
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        return response()->json(['data' => $this->conversations->markRead($conversation)]);
    }

    public function storeNote(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:10000'],
            'file' => ['nullable', 'file', 'max:51200'],
        ]);

        $text = trim((string) ($data['note'] ?? ''));
        $file = $data['file'] ?? null;

        if ($text === '' && ! $file) {
            return response()->json(['message' => 'กรอกข้อความหรือแนบไฟล์อย่างน้อยหนึ่งอย่าง'], 422);
        }

        if ($file) {
            $mime = (string) ($file->getMimeType() ?: '');
            if (
                ! str_starts_with($mime, 'image/')
                && ! str_starts_with($mime, 'video/')
                && ! str_starts_with($mime, 'application/')
                && ! str_starts_with($mime, 'text/')
            ) {
                return response()->json(['message' => 'รองรับเฉพาะรูปภาพ วิดีโอ หรือไฟล์เอกสาร'], 422);
            }
        }

        $note = ConversationNote::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'note' => $text !== '' ? $text : null,
        ]);

        if ($file) {
            $bytes = file_get_contents($file->getRealPath());
            if ($bytes === false) {
                $note->delete();

                return response()->json(['message' => 'อ่านไฟล์ไม่สำเร็จ'], 500);
            }

            $originalName = $file->getClientOriginalName() ?: ('file-'.Str::uuid());
            $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
            $storedPath = $this->storage->put(
                'attachments/'.date('Y/m/').Str::uuid().'_'.$originalName,
                $bytes,
                $mime
            );

            Attachment::query()->create([
                'conversation_note_id' => $note->id,
                'original_name' => $originalName,
                'mime_type' => $mime,
                'size_bytes' => strlen($bytes),
                'storage_disk' => $this->storage->diskName(),
                'storage_path' => $storedPath,
                'download_status' => 'completed',
                'download_attempts' => 0,
            ]);
        }

        $this->audit->log('note.create', $note, [
            'conversation_id' => $conversation->id,
            'has_attachment' => (bool) $file,
        ], $request->user()->id);

        $note = $note->fresh(['user:id,name,display_name', 'attachments']);

        return response()->json([
            'data' => ConversationNote::presentMany([$note])->first(),
        ], 201);
    }

    public function updateNote(Request $request, ConversationNote $note)
    {
        $this->authorizeConversation($request, $note->conversation);

        $data = $request->validate(['note' => ['required', 'string', 'max:10000']]);
        $note->update($data);

        return response()->json([
            'data' => ConversationNote::presentMany([
                $note->fresh(['user:id,name,display_name', 'attachments']),
            ])->first(),
        ]);
    }

    public function destroyNote(Request $request, ConversationNote $note)
    {
        $this->authorizeConversation($request, $note->conversation);
        $note->delete();

        return response()->json(['message' => 'deleted']);
    }

    public function notes(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $notes = $conversation->notes()
            ->with(['user:id,name,display_name', 'attachments'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => ConversationNote::presentMany($notes)->values(),
        ]);
    }

    public function attachTag(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate([
            'tag_id' => ['nullable', 'exists:tags,id'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        if (empty($data['tag_id']) && ! filled($data['name'] ?? null)) {
            return response()->json(['message' => 'ต้องระบุแท็ก'], 422);
        }

        if (! empty($data['tag_id'])) {
            $tag = Tag::query()->findOrFail($data['tag_id']);
        } else {
            $name = ltrim(trim((string) $data['name']), '#');
            if ($name === '') {
                return response()->json(['message' => 'ชื่อแท็กไม่ถูกต้อง'], 422);
            }

            $tag = Tag::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if (! $tag) {
                $tag = Tag::query()->create(['name' => $name]);
            }
        }

        $conversation->tags()->syncWithoutDetaching([$tag->id]);

        return response()->json(['data' => $this->loadConversation($conversation)]);
    }

    public function detachTag(Request $request, Conversation $conversation, Tag $tag)
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->tags()->detach($tag->id);

        return response()->json(['data' => $this->loadConversation($conversation)]);
    }

    public function media(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $type = (string) $request->get('type', 'all');
        $query = Attachment::query()
            ->where('download_status', 'completed')
            ->whereNotNull('storage_path')
            ->whereHas('message', fn ($q) => $q->where('conversation_id', $conversation->id))
            ->with(['message:id,conversation_id,message_type,created_at,direction,line_account_id'])
            ->orderByDesc('id');

        $this->applyMediaTypeFilter($query, $type);

        $items = $query->limit(min((int) $request->get('limit', 100), 200))->get()
            ->map(fn (Attachment $attachment) => $this->presentMediaAttachment($attachment));

        return response()->json(['data' => $items->values()]);
    }

    private function applyMediaTypeFilter($query, string $type): void
    {
        match ($type) {
            'image' => $query->where('mime_type', 'like', 'image/%'),
            'video' => $query->where('mime_type', 'like', 'video/%'),
            'audio' => $query->where('mime_type', 'like', 'audio/%'),
            'file' => $query->where(function ($q) {
                $q->whereNull('mime_type')
                    ->orWhere(function ($inner) {
                        $inner->where('mime_type', 'not like', 'image/%')
                            ->where('mime_type', 'not like', 'video/%')
                            ->where('mime_type', 'not like', 'audio/%');
                    });
            }),
            default => null,
        };
    }

    private function presentMediaAttachment(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'kind' => $attachment->mediaKind(),
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'original_name' => $attachment->original_name,
            'download_name' => $attachment->download_name,
            'download_status' => $attachment->download_status,
            'created_at' => $attachment->created_at,
            'url' => URL::temporarySignedRoute(
                'attachments.download',
                now()->addHour(),
                ['attachment' => $attachment->id],
                absolute: false,
            ),
            'message' => $attachment->message ? [
                'id' => $attachment->message->id,
                'message_type' => $attachment->message->message_type,
                'direction' => $attachment->message->direction,
                'created_at' => $attachment->message->created_at,
            ] : null,
        ];
    }

    private function loadConversation(Conversation $conversation): Conversation
    {
        return $conversation->fresh([
            'lineAccount:id,name,account_color,slug,uuid',
            'lineIdentity',
            'contact.identities.lineAccount:id,name,account_color',
            'assignee:id,name,display_name',
            'tags',
        ]) ?? $conversation->load([
            'lineAccount:id,name,account_color,slug,uuid',
            'lineIdentity',
            'contact.identities.lineAccount:id,name,account_color',
            'assignee:id,name,display_name',
            'tags',
        ]);
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        abort_unless($request->user()->can('inbox.view'), 403);
        abort_unless(in_array($conversation->line_account_id, $request->user()->accessibleLineAccountIds(), true), 403);
    }
}
