<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['group' => 'general', 'key' => 'system.name', 'value' => 'STECH LINE OA CONSOLE'],
            ['group' => 'general', 'key' => 'system.timezone', 'value' => 'Asia/Bangkok'],
            ['group' => 'general', 'key' => 'system.date_format', 'value' => 'd/m/Y H:i'],
            ['group' => 'chat', 'key' => 'chat.auto_reopen', 'value' => '1'],
            ['group' => 'chat', 'key' => 'chat.polling_active_seconds', 'value' => '3'],
            ['group' => 'chat', 'key' => 'chat.polling_inbox_seconds', 'value' => '5'],
            ['group' => 'chat', 'key' => 'chat.polling_hidden_seconds', 'value' => '25'],
            ['group' => 'chat', 'key' => 'chat.sound_notification', 'value' => '1'],
            ['group' => 'contact', 'key' => 'contact.ask_student_code', 'value' => '0'],
            ['group' => 'contact', 'key' => 'contact.allow_staff_edit_student_code', 'value' => '1'],
            ['group' => 'contact', 'key' => 'contact.allow_merge', 'value' => '1'],
            ['group' => 'storage', 'key' => 'storage.driver', 'value' => 'local'],
            ['group' => 'retention', 'key' => 'retention.message_days', 'value' => '0'],
            ['group' => 'retention', 'key' => 'retention.raw_webhook_days', 'value' => '30'],
            ['group' => 'retention', 'key' => 'retention.attachment_days', 'value' => '0'],
        ];

        foreach ($defaults as $item) {
            Setting::query()->updateOrCreate(
                ['key' => $item['key']],
                ['group' => $item['group'], 'value' => $item['value']]
            );
        }
    }
}
