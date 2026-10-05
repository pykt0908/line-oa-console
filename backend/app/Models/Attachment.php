<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = [
        'message_id',
        'conversation_note_id',
        'original_name',
        'mime_type',
        'size_bytes',
        'storage_disk',
        'storage_path',
        'external_file_id',
        'thumbnail_path',
        'download_status',
        'download_attempts',
    ];

    protected $appends = [
        'download_name',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function conversationNote(): BelongsTo
    {
        return $this->belongsTo(ConversationNote::class);
    }

    public function uiUrl(int $minutes = 60): ?string
    {
        if ($this->download_status !== 'completed' || ! $this->storage_path) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'attachments.download',
            now()->addMinutes($minutes),
            ['attachment' => $this->id],
            absolute: false,
        );
    }

    public function getDownloadNameAttribute(): string
    {
        return static::resolveDownloadName($this->original_name, $this->mime_type);
    }

    public static function resolveDownloadName(?string $originalName, ?string $mimeType): string
    {
        $name = trim((string) $originalName);
        if ($name === '') {
            $name = 'file';
        }

        $name = basename(str_replace(["\0", '\\'], '', $name));
        $mime = strtolower(trim(explode(';', (string) $mimeType)[0]));
        $ext = static::extensionFromMime($mime);

        if ($ext && preg_match('/\.bin$/i', $name)) {
            return (string) preg_replace('/\.bin$/i', '.'.$ext, $name);
        }

        if ($ext && ! pathinfo($name, PATHINFO_EXTENSION)) {
            return $name.'.'.$ext;
        }

        return $name;
    }

    public static function extensionFromMime(?string $mime): ?string
    {
        return match (strtolower(trim(explode(';', (string) $mime)[0]))) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/mpeg' => 'mp3',
            'application/pdf' => 'pdf',
            'application/zip', 'application/x-zip-compressed' => 'zip',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            default => null,
        };
    }

    public function isImage(): bool
    {
        return is_string($this->mime_type) && str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return is_string($this->mime_type) && str_starts_with($this->mime_type, 'video/');
    }

    public function isAudio(): bool
    {
        return is_string($this->mime_type) && str_starts_with($this->mime_type, 'audio/');
    }

    public function mediaKind(): string
    {
        if ($this->isImage()) {
            return 'image';
        }
        if ($this->isVideo()) {
            return 'video';
        }
        if ($this->isAudio()) {
            return 'audio';
        }

        return 'file';
    }
}
