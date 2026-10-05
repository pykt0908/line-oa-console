<?php

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ArchiveLineAttachment implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $attachmentId) {}

    public function handle(AttachmentService $attachments): void
    {
        $attachment = Attachment::query()->with('message.lineAccount')->find($this->attachmentId);

        if (! $attachment || $attachment->download_status === 'completed') {
            return;
        }

        $account = $attachment->message?->lineAccount;
        if (! $account) {
            $attachment->update(['download_status' => 'failed']);

            return;
        }

        $attachments->downloadFromLine($attachment, $account);
    }
}
