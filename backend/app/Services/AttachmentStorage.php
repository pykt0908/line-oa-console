<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentStorage
{
    public function __construct(private GoogleDriveService $googleDrive) {}

    public function diskName(): string
    {
        $fallback = config('filesystems.disks.google-drive.enabled')
            ? 'google-drive'
            : config('filesystems.default', 'local');
        $driver = Setting::getValue('storage.driver', $fallback);

        return match ($driver) {
            'google', 'google-drive', 'gdrive' => 'google-drive',
            's3' => 's3',
            's3-compatible' => 's3-compatible',
            default => 'local',
        };
    }

    public function usesGoogleDrive(): bool
    {
        return $this->diskName() === 'google-drive';
    }

    public function put(string $pathOrName, string $contents, ?string $mimeType = null): string
    {
        if ($this->usesGoogleDrive()) {
            $uploaded = $this->googleDrive->upload(basename($pathOrName), $contents, $mimeType);

            return $uploaded['id'];
        }

        Storage::disk($this->diskName())->put($pathOrName, $contents);

        return $pathOrName;
    }

    public function get(string $disk, string $path): string
    {
        if ($disk === 'google-drive') {
            return $this->googleDrive->download($path);
        }

        return Storage::disk($disk)->get($path);
    }

    public function downloadResponse(string $disk, string $path, string $downloadName, ?string $mime = null): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        if ($disk === 'google-drive') {
            $bytes = $this->googleDrive->download($path);

            return response($bytes, 200, [
                'Content-Type' => $mime ?: 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.$downloadName.'"',
                'Content-Length' => (string) strlen($bytes),
            ]);
        }

        return Storage::disk($disk)->download($path, $downloadName);
    }
}
