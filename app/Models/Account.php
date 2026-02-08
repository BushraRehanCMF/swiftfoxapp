<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    use HasFactory;

    /**
     * Subscription statuses
     */
    public const STATUS_TRIAL = 'trial';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'trial_ends_at',
        'subscription_status',
        'conversations_used',
        'conversations_limit',
        'timezone',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'conversations_used' => 'integer',
            'conversations_limit' => 'integer',
        ];
    }

    /**
     * Get the users for the account.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the owner of the account.
     */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('role', User::ROLE_OWNER);
    }

    /**
     * Get the WhatsApp connection for the account.
     */
    public function whatsappConnection(): HasOne
    {
        return $this->hasOne(WhatsappConnection::class);
    }

    /**
     * Get the contacts for the account.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Get the conversations for the account.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get the messages for the account.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the labels for the account.
     */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    /**
     * Get the automation rules for the account.
     */
    public function automationRules(): HasMany
    {
        return $this->hasMany(AutomationRule::class);
    }

    /**
     * Get the business hours for the account.
     */
    public function businessHours(): HasMany
    {
        return $this->hasMany(BusinessHour::class);
    }

    /**
     * Check if the account is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->subscription_status === self::STATUS_TRIAL
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the trial has expired.
     */
    public function isTrialExpired(): bool
    {
        return $this->subscription_status === self::STATUS_TRIAL
            && $this->trial_ends_at
            && $this->trial_ends_at->isPast();
    }

    /**
     * Check if the account has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription_status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the account can send messages.
     */
    public function canSendMessages(): bool
    {
        // Must have active subscription or be on valid trial
        if (!$this->hasActiveSubscription() && !$this->isOnTrial()) {
            return false;
        }

        // Must not have exceeded conversation limit
        if ($this->conversations_used >= $this->conversations_limit) {
            return false;
        }

        return true;
    }

    /**
     * Check if the account has WhatsApp connected.
     */
    public function hasWhatsappConnected(): bool
    {
        return $this->whatsappConnection()
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get remaining conversations.
     */
    public function getRemainingConversations(): int
    {
        return max(0, $this->conversations_limit - $this->conversations_used);
    }

    /**
     * Get remaining trial days.
     */
    public function getRemainingTrialDays(): int
    {
        if (!$this->trial_ends_at) {
            return 0;
        }

        return (int) max(0, now()->diffInDays($this->trial_ends_at, false));
    }

    /**
     * Increment the conversation count.
     */
    public function incrementConversationCount(): void
    {
        $this->increment('conversations_used');
    }
}
