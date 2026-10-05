<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('student_code', 32)->nullable()->index();
            $table->string('contact_type', 32)->default('unknown');
            $table->text('note')->nullable();
            $table->foreignId('merged_into_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->timestamp('merged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('line_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_account_id')->constrained()->cascadeOnDelete();
            $table->string('line_user_id');
            $table->string('display_name')->nullable();
            $table->string('picture_url', 1024)->nullable();
            $table->string('status_message', 512)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->unique(['line_account_id', 'line_user_id']);
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_identities');
        Schema::dropIfExists('contacts');
    }
};
