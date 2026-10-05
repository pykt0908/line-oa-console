<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Message;
use App\Services\AuditService;
use App\Services\ContactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ContactController extends Controller
{
    public function __construct(
        private ContactService $contacts,
        private AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->can('inbox.view'), 403);

        $accountIds = $request->user()->accessibleLineAccountIds();

        $query = Contact::query()
            ->with(['identities.lineAccount:id,name,account_color'])
            ->whereNull('merged_into_contact_id')
            ->whereHas('identities', fn ($q) => $q->whereIn('line_account_id', $accountIds ?: [-1]))
            ->withCount('identities')
            ->latest('updated_at');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($builder) use ($q) {
                $builder->where('student_code', 'like', "%{$q}%")
                    ->orWhereHas('identities', fn ($i) => $i->where('display_name', 'like', "%{$q}%"));
            });
        }

        if ($request->get('has_student_code') === '1') {
            $query->whereNotNull('student_code');
        } elseif ($request->get('has_student_code') === '0') {
            $query->whereNull('student_code');
        }

        return response()->json($query->paginate(min((int) $request->get('per_page', 30), 100)));
    }

    public function show(Request $request, Contact $contact)
    {
        abort_unless($request->user()->can('inbox.view'), 403);
        $this->authorizeContact($request, $contact);

        $contact->load([
            'identities.lineAccount:id,name,account_color,slug',
            'conversations' => fn ($q) => $q->with(['lineAccount:id,name,account_color', 'assignee:id,name,display_name'])->latest('last_message_at'),
        ]);

        return response()->json(['data' => $contact]);
    }

    public function update(Request $request, Contact $contact)
    {
        abort_unless($request->user()->can('contact.edit'), 403);
        $this->authorizeContact($request, $contact);

        $data = $request->validate([
            'note' => ['nullable', 'string'],
            'contact_type' => ['sometimes', 'in:student,other,unknown'],
        ]);

        $contact->update($data);

        return response()->json(['data' => $contact->fresh('identities.lineAccount')]);
    }

    public function setStudentCode(Request $request, Contact $contact)
    {
        abort_unless($request->user()->can('contact.edit'), 403);
        $this->authorizeContact($request, $contact);

        $data = $request->validate([
            'student_code' => ['required', 'string', 'regex:/^\d{1,11}$/'],
            'merge' => ['boolean'],
        ], [
            'student_code.regex' => 'รหัสนักศึกษาต้องเป็นตัวเลข ไม่เกิน 11 หลัก',
        ]);

        $result = $this->contacts->setStudentCode($contact, $data['student_code'], $data['merge'] ?? false);

        if ($result['status'] === 'duplicate') {
            return response()->json([
                'message' => 'พบรหัสนักศึกษานี้ในระบบแล้ว',
                'existing_contact' => $result['existing_contact'],
            ], 409);
        }

        return response()->json($result);
    }

    public function merge(Request $request, Contact $contact)
    {
        abort_unless($request->user()->can('contact.merge'), 403);
        $this->authorizeContact($request, $contact);

        $data = $request->validate([
            'target_contact_id' => ['required', 'exists:contacts,id'],
        ]);

        $target = Contact::query()->findOrFail($data['target_contact_id']);
        $merged = $this->contacts->merge($target, $contact);
        $this->audit->log('contact.merge', $merged, [
            'source_contact_id' => $contact->id,
            'target_contact_id' => $target->id,
        ]);

        return response()->json(['data' => $merged]);
    }

    public function unifiedHistory(Request $request, Contact $contact)
    {
        abort_unless($request->user()->can('inbox.view'), 403);
        $this->authorizeContact($request, $contact);

        $conversationIds = $contact->conversations()->pluck('id');

        $messages = Message::query()
            ->with(['lineAccount:id,name,account_color', 'sender:id,name,display_name', 'attachments'])
            ->whereIn('conversation_id', $conversationIds)
            ->orderByDesc('id')
            ->paginate(min((int) $request->get('per_page', 50), 100));

        Message::presentMany($messages->getCollection());

        return response()->json($messages);
    }

    public function media(Request $request, Contact $contact)
    {
        abort_unless($request->user()->can('inbox.view'), 403);
        $this->authorizeContact($request, $contact);

        $conversationIds = $contact->conversations()->pluck('id');
        $type = (string) $request->get('type', 'all');

        $query = Attachment::query()
            ->where('download_status', 'completed')
            ->whereNotNull('storage_path')
            ->whereHas('message', fn ($q) => $q->whereIn('conversation_id', $conversationIds ?: [-1]))
            ->with(['message:id,conversation_id,message_type,created_at,direction,line_account_id'])
            ->orderByDesc('id');

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

        $items = $query->limit(min((int) $request->get('limit', 100), 200))->get()
            ->map(function (Attachment $attachment) {
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
            });

        return response()->json(['data' => $items->values()]);
    }

    private function authorizeContact(Request $request, Contact $contact): void
    {
        $accountIds = $request->user()->accessibleLineAccountIds();
        $allowed = $contact->identities()->whereIn('line_account_id', $accountIds ?: [-1])->exists();
        abort_unless($allowed || $request->user()->hasAnyRole(['Super Admin', 'Admin']), 403);
    }
}
