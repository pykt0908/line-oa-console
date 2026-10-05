<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\AttachmentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AttachmentController extends Controller
{
    public function __construct(private AttachmentStorage $storage) {}

    public function signedUrl(Request $request, Attachment $attachment)
    {
        abort_unless($request->user()->can('inbox.view'), 403);

        $conversation = $attachment->message?->conversation;
        abort_unless($conversation, 404);
        abort_unless(in_array($conversation->line_account_id, $request->user()->accessibleLineAccountIds(), true), 403);

        $url = URL::temporarySignedRoute(
            'attachments.download',
            now()->addMinutes(30),
            ['attachment' => $attachment->id],
            absolute: false,
        );

        return response()->json(['url' => $url]);
    }

    public function download(Request $request, Attachment $attachment)
    {
        // Accept relative (LINE/tunnel) or absolute (same-host) signatures.
        if (! $request->hasValidSignature(absolute: false) && ! $request->hasValidSignature()) {
            abort(403);
        }

        abort_unless($attachment->storage_path && $attachment->download_status === 'completed', 404);

        $fileName = $attachment->download_name;
        $mime = $attachment->mime_type ?: 'application/octet-stream';
        $inline = $attachment->isImage() || $attachment->isVideo() || str_starts_with($mime, 'audio/');

        $response = $this->storage->downloadResponse(
            $attachment->storage_disk,
            $attachment->storage_path,
            $fileName,
            $mime
        );

        $disposition = $inline ? 'inline' : 'attachment';
        $safeAscii = preg_replace('/[^\x20-\x7E]/', '_', $fileName) ?: 'file';
        $utf8 = rawurlencode($fileName);

        $response->headers->set('Content-Type', $mime);
        $response->headers->set(
            'Content-Disposition',
            $disposition.'; filename="'.$safeAscii.'"; filename*=UTF-8\'\''.$utf8
        );

        return $response;
    }
}
