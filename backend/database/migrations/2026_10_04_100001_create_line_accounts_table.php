<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('channel_id')->nullable();
            $table->text('channel_secret');
            $table->text('channel_access_token');
            $table->string('bot_user_id')->nullable();
            $table->string('account_color', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamps();
        });

        Schema::create('line_account_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['line_account_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_account_user');
        Schema::dropIfExists('line_accounts');
    }
};
