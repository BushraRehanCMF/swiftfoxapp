<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappConnection extends Model
{
    use HasFactory;

    /**
     * Connection statuses
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISCONNECTED = 'disconnected';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'waba_id',
        'phone_number_id',
        'phone_number',
        'status',
    ];

    /**
     * Get the account that owns the WhatsApp connection.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Check if the connection is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
