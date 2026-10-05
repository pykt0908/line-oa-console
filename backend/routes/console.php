<?php

use App\Jobs\ArchiveLineAttachment;
use App\Models\Attachment;
use App\Models\Setting;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::call(function () {
    Attachment::query()
        ->where('download_status', 'pending')
        ->where('download_attempts', '<', 3)
        ->limit(20)
        ->each(fn (Attachment $a) => ArchiveLineAttachment::dispatch($a->id));
})->everyFiveMinutes()->name('retry-attachments')->withoutOverlapping();

Schedule::call(function () {
    $days = (int) Setting::getValue('retention.raw_webhook_days', 30);
    WebhookEvent::query()
        ->where('created_at', '<', now()->subDays(max($days, 1)))
        ->delete();
})->daily()->name('cleanup-webhooks');

Schedule::call(function () {
    $disk = Storage::disk('local');
    foreach ($disk->files('temp') as $file) {
        if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
            $disk->delete($file);
        }
    }
})->daily()->name('cleanup-temp');
