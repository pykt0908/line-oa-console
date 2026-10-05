<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_identity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('open')->index();
            $table->string('priority', 32)->default('normal');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->index(['line_account_id', 'status']);
            $table->index('assigned_user_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_identity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 16);
            $table->string('sender_type', 32);
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('line_message_id')->nullable();
            $table->string('webhook_event_id')->nullable();
            $table->string('message_type', 32)->default('text');
            $table->text('text_content')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('delivery_status', 32)->default('received');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('raw_payload_json')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
            $table->index(['conversation_id', 'created_at']);
            $table->unique('webhook_event_id');
            $table->unique(['line_account_id', 'line_message_id', 'direction'], 'messages_line_dedup_unique');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('last_message_id')->references('id')->on('messages')->nullOnDelete();
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_account_id')->constrained()->cascadeOnDelete();
            $table->string('event_id')->nullable()->unique();
            $table->string('event_type')->nullable();
            $table->string('status', 32)->default('pending');
            $table->json('payload');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['last_message_id']);
        });
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
