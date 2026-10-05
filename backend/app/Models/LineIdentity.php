<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LineIdentity extends Model
{
    protected $fillable = [
        'contact_id',
        'line_account_id',
        'line_user_id',
        'display_name',
        'picture_url',
        'status_message',
        'first_seen_at',
        'last_seen_at',
        'blocked_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lineAccount(): BelongsTo
    {
        return $this->belongsTo(LineAccount::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
