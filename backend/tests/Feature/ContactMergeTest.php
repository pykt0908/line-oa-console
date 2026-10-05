<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\LineAccount;
use App\Models\LineIdentity;
use App\Models\Message;
use App\Models\User;
use App\Services\ContactService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_keeps_all_messages(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $accountA = LineAccount::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'วิชาการ',
            'slug' => 'academic-'.Str::random(4),
            'channel_secret' => 'secret-a',
            'channel_access_token' => 'token-a',
        ]);
        $accountB = LineAccount::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'IT',
            'slug' => 'it-'.Str::random(4),
            'channel_secret' => 'secret-b',
            'channel_access_token' => 'token-b',
        ]);

        $target = Contact::query()->create(['student_code' => '68001234', 'contact_type' => 'student']);
        $source = Contact::query()->create(['student_code' => '68001234', 'contact_type' => 'student']);

        $idA = LineIdentity::query()->create([
            'contact_id' => $target->id,
            'line_account_id' => $accountA->id,
            'line_user_id' => 'Ua',
            'display_name' => 'Bank',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $idB = LineIdentity::query()->create([
            'contact_id' => $source->id,
            'line_account_id' => $accountB->id,
            'line_user_id' => 'Ub',
            'display_name' => 'Bank',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $convA = Conversation::query()->create([
            'line_account_id' => $accountA->id,
            'line_identity_id' => $idA->id,
            'contact_id' => $target->id,
            'status' => 'open',
        ]);
        $convB = Conversation::query()->create([
            'line_account_id' => $accountB->id,
            'line_identity_id' => $idB->id,
            'contact_id' => $source->id,
            'status' => 'open',
        ]);

        Message::query()->create([
            'conversation_id' => $convA->id,
            'line_account_id' => $accountA->id,
            'line_identity_id' => $idA->id,
            'direction' => 'inbound',
            'sender_type' => 'line_user',
            'message_type' => 'text',
            'text_content' => 'จากวิชาการ',
            'delivery_status' => 'received',
        ]);
        Message::query()->create([
            'conversation_id' => $convB->id,
            'line_account_id' => $accountB->id,
            'line_identity_id' => $idB->id,
            'direction' => 'inbound',
            'sender_type' => 'line_user',
            'message_type' => 'text',
            'text_content' => 'จาก IT',
            'delivery_status' => 'received',
        ]);

        $before = Message::query()->count();
        $merged = app(ContactService::class)->merge($target, $source);

        $this->assertSame($before, Message::query()->count());
        $this->assertSame($target->id, $merged->id);
        $this->assertSame(2, $merged->identities()->count());
        $this->assertNotNull($source->fresh()->merged_into_contact_id);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->syncRoles(['Super Admin']);
        $admin->lineAccounts()->sync([$accountA->id, $accountB->id]);

        $this->actingAs($admin)
            ->getJson("/api/contacts/{$target->id}/unified-history")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
