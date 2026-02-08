<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRule extends Model
{
    use BelongsToAccount, HasFactory;

    /**
     * Trigger types
     */
    public const TRIGGER_MESSAGE_RECEIVED = 'message_received';
    public const TRIGGER_CONVERSATION_OPENED = 'conversation_opened';
    public const TRIGGER_KEYWORD_MATCHED = 'keyword_matched';

    /**
     * Action types
     */
    public const ACTION_ASSIGN_USER = 'assign_user';
    public const ACTION_ADD_LABEL = 'add_label';
    public const ACTION_SEND_REPLY = 'send_reply';

    /**
     * All available trigger types
     */
    public const TRIGGER_TYPES = [
        self::TRIGGER_MESSAGE_RECEIVED => 'Message Received',
        self::TRIGGER_CONVERSATION_OPENED => 'Conversation Opened',
        self::TRIGGER_KEYWORD_MATCHED => 'Keyword Matched',
    ];

    /**
     * All available action types
     */
    public const ACTION_TYPES = [
        self::ACTION_ASSIGN_USER => 'Assign to User',
        self::ACTION_ADD_LABEL => 'Add Label',
        self::ACTION_SEND_REPLY => 'Send Auto Reply',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'name',
        'trigger_type',
        'conditions',
        'actions',
        'is_enabled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'actions' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * Get the account that owns the automation rule.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Check if the rule is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    /**
     * Scope to only enabled rules.
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
