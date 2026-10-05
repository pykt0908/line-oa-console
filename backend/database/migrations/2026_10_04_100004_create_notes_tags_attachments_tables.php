<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['conversation_id', 'tag_id']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('storage_disk')->default('local');
            $table->string('storage_path')->nullable();
            $table->string('external_file_id')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('download_status', 32)->default('pending');
            $table->unsignedTinyInteger('download_attempts')->default(0);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('action');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group')->default('general');
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // FULLTEXT for message search (MariaDB/MySQL InnoDB)
        try {
            DB::statement('ALTER TABLE messages ADD FULLTEXT messages_text_fulltext (text_content)');
        } catch (\Throwable) {
            // Hosting may not support FULLTEXT; LIKE fallback remains.
        }
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE messages DROP INDEX messages_text_fulltext');
        } catch (\Throwable) {
        }

        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('conversation_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('conversation_notes');
    }
};
