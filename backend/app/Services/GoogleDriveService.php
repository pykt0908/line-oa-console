<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleDriveService
{
    private ?Drive $drive = null;

    public function isConfigured(): bool
    {
        if (filled(config('filesystems.disks.google-drive.service_account_json'))) {
            $path = config('filesystems.disks.google-drive.service_account_json');

            return is_string($path) && is_file($path);
        }

        return filled(config('filesystems.disks.google-drive.client_id'))
            && filled(config('filesystems.disks.google-drive.client_secret'))
            && filled(config('filesystems.disks.google-drive.refresh_token'));
    }

    public function client(): GoogleClient
    {
        $client = new GoogleClient;
        $client->setApplicationName(config('app.name', 'LINE OA Console'));
        $client->setScopes([Drive::DRIVE_FILE]);
        $client->setAccessType('offline');

        $sa = config('filesystems.disks.google-drive.service_account_json');
        if (filled($sa) && is_file((string) $sa)) {
            $client->setAuthConfig($sa);
            $client->setScopes([Drive::DRIVE]);

            return $client;
        }

        $client->setClientId((string) config('filesystems.disks.google-drive.client_id'));
        $client->setClientSecret((string) config('filesystems.disks.google-drive.client_secret'));
        $client->fetchAccessTokenWithRefreshToken((string) config('filesystems.disks.google-drive.refresh_token'));

        return $client;
    }

    public function drive(): Drive
    {
        return $this->drive ??= new Drive($this->client());
    }

    public function folderId(): string
    {
        $folder = (string) (config('filesystems.disks.google-drive.folder_id') ?: '');
        if ($folder !== '' && $folder !== 'root') {
            return $folder;
        }

        $shared = (string) (config('filesystems.disks.google-drive.shared_drive_id') ?: '');
        if ($shared !== '') {
            return $shared;
        }

        return $folder !== '' ? $folder : 'root';
    }

    /**
     * @return array{id: string, name: string, size: int|null, mime: string|null}
     */
    public function upload(string $name, string $contents, ?string $mimeType = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Drive is not configured. See docs/GOOGLE_DRIVE.md');
        }

        $meta = new DriveFile([
            'name' => $name,
            'parents' => [$this->folderId()],
        ]);

        $created = $this->drive()->files->create($meta, [
            'data' => $contents,
            'mimeType' => $mimeType ?: 'application/octet-stream',
            'uploadType' => 'multipart',
            'fields' => 'id,name,size,mimeType',
            'supportsAllDrives' => true,
        ]);

        return [
            'id' => $created->getId(),
            'name' => $created->getName(),
            'size' => $created->getSize() !== null ? (int) $created->getSize() : strlen($contents),
            'mime' => $created->getMimeType(),
        ];
    }

    public function download(string $fileId): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Drive is not configured.');
        }

        $response = $this->drive()->files->get($fileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);

        return $response->getBody()->getContents();
    }

    public function delete(string $fileId): void
    {
        try {
            $this->drive()->files->delete($fileId, ['supportsAllDrives' => true]);
        } catch (\Throwable $e) {
            Log::warning('Google Drive delete failed', ['file_id' => $fileId, 'error' => $e->getMessage()]);
        }
    }

    public function testConnection(): array
    {
        $about = $this->drive()->about->get(['fields' => 'user']);
        $folderId = $this->folderId();
        $folder = $this->drive()->files->get($folderId, [
            'fields' => 'id,name',
            'supportsAllDrives' => true,
        ]);

        return [
            'ok' => true,
            'user' => $about->getUser()?->getEmailAddress(),
            'folder_id' => $folder->getId(),
            'folder_name' => $folder->getName(),
        ];
    }
}
