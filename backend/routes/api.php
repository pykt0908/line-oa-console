<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LineAccountController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/line/{lineAccountUuid}', [WebhookController::class, 'handle']);

Route::middleware('web')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::patch('/conversations/{conversation}', [ConversationController::class, 'update']);
    Route::post('/conversations/{conversation}/assign', [ConversationController::class, 'assign']);
    Route::post('/conversations/{conversation}/resolve', [ConversationController::class, 'resolve']);
    Route::post('/conversations/{conversation}/reopen', [ConversationController::class, 'reopen']);
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage']);
    Route::post('/conversations/{conversation}/media', [ConversationController::class, 'sendMedia']);
    Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead']);
    Route::get('/conversations/{conversation}/notes', [ConversationController::class, 'notes']);
    Route::post('/conversations/{conversation}/notes', [ConversationController::class, 'storeNote']);
    Route::patch('/notes/{note}', [ConversationController::class, 'updateNote']);
    Route::delete('/notes/{note}', [ConversationController::class, 'destroyNote']);
    Route::post('/conversations/{conversation}/tags', [ConversationController::class, 'attachTag']);
    Route::delete('/conversations/{conversation}/tags/{tag}', [ConversationController::class, 'detachTag']);
    Route::get('/conversations/{conversation}/media', [ConversationController::class, 'media']);

    Route::get('/contacts', [ContactController::class, 'index']);
    Route::get('/contacts/{contact}', [ContactController::class, 'show']);
    Route::patch('/contacts/{contact}', [ContactController::class, 'update']);
    Route::post('/contacts/{contact}/student-code', [ContactController::class, 'setStudentCode']);
    Route::post('/contacts/{contact}/merge', [ContactController::class, 'merge']);
    Route::get('/contacts/{contact}/unified-history', [ContactController::class, 'unifiedHistory']);
    Route::get('/contacts/{contact}/media', [ContactController::class, 'media']);

    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);
    Route::delete('/tags/{tag}', [TagController::class, 'destroy']);

    Route::get('/line-accounts', [LineAccountController::class, 'index']);
    Route::post('/line-accounts', [LineAccountController::class, 'store']);
    Route::get('/line-accounts/{lineAccount}', [LineAccountController::class, 'show']);
    Route::patch('/line-accounts/{lineAccount}', [LineAccountController::class, 'update']);
    Route::post('/line-accounts/{lineAccount}/test', [LineAccountController::class, 'test']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::patch('/users/{user}', [UserController::class, 'update']);
    Route::get('/roles', [UserController::class, 'roles']);

    Route::get('/settings', [SettingController::class, 'index']);
    Route::put('/settings', [SettingController::class, 'update']);

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    Route::get('/attachments/{attachment}/url', [AttachmentController::class, 'signedUrl']);
});

Route::get('/attachments/{attachment}/download', [AttachmentController::class, 'download'])
    ->name('attachments.download');
