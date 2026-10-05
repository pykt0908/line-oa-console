<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveService;
use Illuminate\Console\Command;

class GoogleDriveTestCommand extends Command
{
    protected $signature = 'drive:test';

    protected $description = 'Test Google Drive credentials and folder access';

    public function handle(GoogleDriveService $drive): int
    {
        if (! $drive->isConfigured()) {
            $this->error('Google Drive is not configured.');
            $this->line('Set either:');
            $this->line('  GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON + GOOGLE_DRIVE_ROOT_FOLDER_ID');
            $this->line('or GOOGLE_DRIVE_CLIENT_ID / CLIENT_SECRET / REFRESH_TOKEN');
            $this->line('See docs/GOOGLE_DRIVE.md');

            return self::FAILURE;
        }

        try {
            $result = $drive->testConnection();
            $this->info('Google Drive OK');
            $this->table(['Field', 'Value'], [
                ['User', $result['user'] ?? '-'],
                ['Folder ID', $result['folder_id']],
                ['Folder Name', $result['folder_name']],
            ]);

            $probe = $drive->upload(
                'line-oa-console-probe-'.now()->format('YmdHis').'.txt',
                'probe '.now()->toIso8601String(),
                'text/plain'
            );
            $this->info('Uploaded probe file: '.$probe['id']);
            $drive->delete($probe['id']);
            $this->info('Deleted probe file.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
