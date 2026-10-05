<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'line_account_id',
        'line_identity_id',
        'direction',
        'sender_type',
        'sender_user_id',
        'line_message_id',
        'webhook_event_id',
        'message_type',
        'text_content',
        'sent_at',
        'received_at',
        'delivery_status',
        'error_code',
        'error_message',
        'raw_payload_json',
    ];

    protected $hidden = [
        'raw_payload_json',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'raw_payload_json' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function lineAccount(): BelongsTo
    {
        return $this->belongsTo(LineAccount::class);
    }

    public function lineIdentity(): BelongsTo
    {
        return $this->belongsTo(LineIdentity::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Add signed attachment URLs + sticker preview for the chat UI.
     */
    public function presentForChat(): static
    {
        $this->makeHidden(['raw_payload_json']);

        if ($this->relationLoaded('attachments')) {
            $this->attachments->each(function (Attachment $attachment) {
                if ($url = $attachment->uiUrl()) {
                    $attachment->setAttribute('url', $url);
                }
            });
        }

        if ($this->message_type === 'sticker') {
            $payload = data_get($this->raw_payload_json, 'message', []);
            $stickerId = $payload['stickerId'] ?? null;
            $resource = (string) ($payload['stickerResourceType'] ?? 'STATIC');

            $this->setAttribute('sticker', [
                'package_id' => $payload['packageId'] ?? null,
                'sticker_id' => $stickerId,
                'resource_type' => $resource,
                'keywords' => $payload['keywords'] ?? [],
                'preview_url' => $stickerId ? static::stickerCdnUrl((string) $stickerId, $resource) : null,
            ]);
        }

        return $this;
    }

    /**
     * @param  Collection<int, Message>|EloquentCollection<int, Message>|iterable<Message>  $messages
     * @return Collection<int, Message>
     */
    public static function presentMany(iterable $messages): Collection
    {
        return Collection::make($messages)->each(fn (Message $message) => $message->presentForChat());
    }

    public static function stickerCdnUrl(string $stickerId, string $resourceType = 'STATIC'): string
    {
        $animated = in_array($resourceType, [
            'ANIMATION',
            'ANIMATION_SOUND',
            'POPUP',
            'POPUP_SOUND',
        ], true);

        $file = $animated ? 'sticker_animation.png' : 'sticker.png';

        return "https://stickershop.line-scdn.net/stickershop/v1/sticker/{$stickerId}/android/{$file}";
    }
}
