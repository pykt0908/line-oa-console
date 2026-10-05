<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LineAccount extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'channel_id',
        'channel_secret',
        'channel_access_token',
        'bot_user_id',
        'account_color',
        'is_active',
        'last_webhook_at',
    ];

    protected function casts(): array
    {
        return [
            'channel_secret' => 'encrypted',
            'channel_access_token' => 'encrypted',
            'is_active' => 'boolean',
            'last_webhook_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'channel_secret',
        'channel_access_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (LineAccount $account): void {
            if (empty($account->uuid)) {
                $account->uuid = (string) Str::uuid();
            }
            if (empty($account->slug)) {
                $account->slug = Str::slug($account->name).'-'.Str::lower(Str::random(4));
            }
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function identities(): HasMany
    {
        return $this->hasMany(LineIdentity::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function maskedSecret(): string
    {
        $secret = $this->channel_secret;

        if (! $secret || strlen($secret) < 8) {
            return '********';
        }

        return substr($secret, 0, 4).str_repeat('*', max(8, strlen($secret) - 8)).substr($secret, -4);
    }

    public function webhookUrl(): string
    {
        return url('/api/webhooks/line/'.$this->uuid);
    }
}
