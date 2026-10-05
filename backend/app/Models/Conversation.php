<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'line_account_id',
        'line_identity_id',
        'contact_id',
        'status',
        'priority',
        'assigned_user_id',
        'last_message_id',
        'last_message_at',
        'last_inbound_at',
        'last_outbound_at',
        'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
        ];
    }

    public function lineAccount(): BelongsTo
    {
        return $this->belongsTo(LineAccount::class);
    }

    public function lineIdentity(): BelongsTo
    {
        return $this->belongsTo(LineIdentity::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ConversationNote::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'conversation_tag')->withTimestamps();
    }
}
