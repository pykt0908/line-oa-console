<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\LineAccount;
use App\Models\Message;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AttachmentService
{
    public function __construct(private AttachmentStorage $storage) {}

    public function createPending(
        Message $message,
        string $lineMessageId,
        string $messageType,
        ?string $fileName = null,
    ): Attachment {
        $originalName = $this->initialFileName($lineMessageId, $messageType, $fileName);

        return Attachment::query()->create([
            'message_id' => $message->id,
            'original_name' => $originalName,
            'mime_type' => $this->mimeForType($messageType, $originalName),
            'storage_disk' => $this->storage->diskName(),
            'external_file_id' => $lineMessageId,
            'download_status' => 'pending',
            'download_attempts' => 0,
        ]);
    }

    public function downloadFromLine(Attachment $attachment, LineAccount $account): Attachment
    {
        $attachment->increment('download_attempts');

        if (! $attachment->external_file_id) {
            $attachment->update(['download_status' => 'failed']);

            return $attachment->fresh();
        }

        $response = Http::withToken($account->channel_access_token)
            ->withHeaders(['Accept' => '*/*'])
            ->get("https://api-data.line.me/v2/bot/message/{$attachment->external_file_id}/content");

        if ($response->failed()) {
            $attachment->update([
                'download_status' => $attachment->download_attempts >= 3 ? 'failed' : 'pending',
            ]);

            return $attachment->fresh();
        }

        $bytes = $response->body();
        $mime = $this->normalizeMime($response->header('Content-Type') ?: $attachment->mime_type);
        $originalName = $this->resolveStoredName($attachment, $response, $mime);
        $disk = $this->storage->diskName();
        $logicalPath = 'attachments/'.date('Y/m/').Str::uuid().'_'.$originalName;

        try {
            $storedPath = $this->storage->put($logicalPath, $bytes, $mime);
            $thumbnailPath = null;

            if ($mime && str_starts_with($mime, 'image/') && function_exists('imagecreatefromstring')) {
                $thumbnailPath = $this->makeThumbnail($logicalPath, $bytes);
            }

            $attachment->update([
                'original_name' => $originalName,
                'storage_disk' => $disk,
                'storage_path' => $storedPath,
                'thumbnail_path' => $thumbnailPath,
                'mime_type' => $mime,
                'size_bytes' => strlen($bytes),
                'download_status' => 'completed',
            ]);
        } catch (\Throwable $e) {
            Log::error('Attachment archive failed', [
                'attachment_id' => $attachment->id,
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);
            $attachment->update([
                'download_status' => $attachment->download_attempts >= 3 ? 'failed' : 'pending',
            ]);
        }

        return $attachment->fresh();
    }

    private function resolveStoredName(Attachment $attachment, Response $response, string $mime): string
    {
        $fromHeader = $this->filenameFromDisposition($response->header('Content-Disposition'));
        $candidate = $fromHeader ?: $attachment->original_name ?: ('file-'.$attachment->external_file_id);

        return Attachment::resolveDownloadName($candidate, $mime);
    }

    private function filenameFromDisposition(?string $header): ?string
    {
        if (! $header) {
            return null;
        }

        if (preg_match("/filename\\*=UTF-8''([^;]+)/i", $header, $matches)) {
            return basename(urldecode(trim($matches[1], " \t\n\r\0\x0B\"'")));
        }

        if (preg_match('/filename="?([^";]+)"?/i', $header, $matches)) {
            return basename(trim($matches[1], " \t\n\r\0\x0B\"'"));
        }

        return null;
    }

    private function initialFileName(string $lineMessageId, string $messageType, ?string $fileName): string
    {
        if (filled($fileName)) {
            return basename(str_replace(["\0", '\\'], '', $fileName));
        }

        return $lineMessageId.'.'.$this->extensionForType($messageType);
    }

    private function normalizeMime(?string $mime): string
    {
        $value = strtolower(trim(explode(';', (string) $mime)[0]));

        return $value !== '' ? $value : 'application/octet-stream';
    }

    private function makeThumbnail(string $logicalPath, string $bytes): ?string
    {
        try {
            $image = @imagecreatefromstring($bytes);
            if (! $image) {
                return null;
            }
            $width = imagesx($image);
            $height = imagesy($image);
            $max = 320;
            $ratio = min($max / $width, $max / $height, 1);
            $tw = (int) ($width * $ratio);
            $th = (int) ($height * $ratio);
            $thumb = imagecreatetruecolor($tw, $th);
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, $tw, $th, $width, $height);
            ob_start();
            imagejpeg($thumb, null, 80);
            $data = ob_get_clean();
            imagedestroy($image);
            imagedestroy($thumb);

            $thumbName = preg_replace('/(\.[^.]+)?$/', '_thumb.jpg', basename($logicalPath)) ?: 'thumb.jpg';

            return $this->storage->put('attachments/'.date('Y/m/').$thumbName, $data, 'image/jpeg');
        } catch (\Throwable) {
            return null;
        }
    }

    private function extensionForType(string $type): string
    {
        return match ($type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'm4a',
            'file' => 'bin',
            default => 'bin',
        };
    }

    private function mimeForType(string $type, ?string $fileName = null): string
    {
        $ext = strtolower((string) pathinfo((string) $fileName, PATHINFO_EXTENSION));

        return match (true) {
            $type === 'image' => 'image/jpeg',
            $type === 'video' => 'video/mp4',
            $type === 'audio' => 'audio/mp4',
            $ext === 'pdf' => 'application/pdf',
            $ext === 'png' => 'image/png',
            $ext === 'jpg', $ext === 'jpeg' => 'image/jpeg',
            $ext === 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $ext === 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $ext === 'zip' => 'application/zip',
            default => 'application/octet-stream',
        };
    }
}
