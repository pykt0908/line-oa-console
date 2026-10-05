<?php

namespace Database\Seeders;

use App\Models\LineAccount;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
        ]);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'อ.กิตติ เมธาการ',
                'display_name' => 'อ.กิตติ',
                'position' => 'เจ้าหน้าที่วิชาการ',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $admin->syncRoles(['Super Admin']);

        $accounts = [
            ['name' => 'งานวิชาการ', 'slug' => 'academic', 'account_color' => '#10B981'],
            ['name' => 'งานทะเบียน', 'slug' => 'registration', 'account_color' => '#3B82F6'],
            ['name' => 'IT Support', 'slug' => 'it-support', 'account_color' => '#8B5CF6'],
        ];

        foreach ($accounts as $data) {
            LineAccount::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $data['name'],
                    'channel_id' => 'demo-'.$data['slug'],
                    'channel_secret' => 'demo-secret-'.$data['slug'].'-'.Str::random(16),
                    'channel_access_token' => 'demo-token-'.$data['slug'].'-'.Str::random(32),
                    'account_color' => $data['account_color'],
                    'is_active' => true,
                ]
            );
        }

        $admin->lineAccounts()->sync(LineAccount::query()->pluck('id'));

        foreach (['ฝึกงาน', 'เอกสารคำร้อง', 'การเงิน', 'คะแนน', 'IT', 'เร่งด่วน'] as $tag) {
            Tag::query()->firstOrCreate(['name' => $tag]);
        }
    }
}
