<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ConversationNote extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'note',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function presentForChat(): static
    {
        if ($this->relationLoaded('attachments')) {
            $this->attachments->each(function (Attachment $attachment) {
                if ($url = $attachment->uiUrl()) {
                    $attachment->setAttribute('url', $url);
                }
            });
        }

        return $this;
    }

    /**
     * @param  Collection<int, ConversationNote>|iterable<ConversationNote>  $notes
     * @return Collection<int, ConversationNote>
     */
    public static function presentMany(iterable $notes): Collection
    {
        return Collection::make($notes)->each(fn (ConversationNote $note) => $note->presentForChat());
    }
}
