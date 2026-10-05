<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropForeign(['message_id']);
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('message_id')->nullable()->change();
            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
            $table->foreignId('conversation_note_id')
                ->nullable()
                ->after('message_id')
                ->constrained('conversation_notes')
                ->cascadeOnDelete();
        });

        Schema::table('conversation_notes', function (Blueprint $table) {
            $table->text('note')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropForeign(['conversation_note_id']);
            $table->dropColumn('conversation_note_id');
            $table->dropForeign(['message_id']);
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('message_id')->nullable(false)->change();
            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
        });

        Schema::table('conversation_notes', function (Blueprint $table) {
            $table->text('note')->nullable(false)->change();
        });
    }
};
