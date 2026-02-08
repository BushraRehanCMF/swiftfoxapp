<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use BelongsToAccount, HasFactory;

    /**
     * Conversation statuses
     */
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'contact_id',
        'assigned_user_id',
        'status',
        'last_message_at',
        'conversation_started_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'conversation_started_at' => 'datetime',
        ];
    }

    /**
     * Get the account that owns the conversation.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the contact for the conversation.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Get the assigned user for the conversation.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Get the messages for the conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the last message for the conversation.
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Get the labels for the conversation.
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'conversation_label');
    }

    /**
     * Check if the conversation is open.
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if this is within the 24-hour messaging window.
     */
    public function isWithinMessagingWindow(): bool
    {
        if (!$this->conversation_started_at) {
            return false;
        }

        return $this->conversation_started_at->addHours(24)->isFuture();
    }
}
