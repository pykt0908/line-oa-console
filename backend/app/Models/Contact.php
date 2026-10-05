<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = [
        'student_code',
        'contact_type',
        'note',
        'merged_into_contact_id',
        'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'merged_at' => 'datetime',
        ];
    }

    public function identities(): HasMany
    {
        return $this->hasMany(LineIdentity::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'merged_into_contact_id');
    }

    public function isMerged(): bool
    {
        return $this->merged_into_contact_id !== null;
    }

    public function hasStudentCode(): bool
    {
        return filled($this->student_code);
    }
}
