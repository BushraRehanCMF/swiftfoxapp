<?php

namespace App\Models\Traits;

use App\Models\Account;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for models that belong to an account (multi-tenancy).
 */
trait BelongsToAccount
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToAccount(): void
    {
        // Automatically scope queries to the current user's account
        static::addGlobalScope('account', function (Builder $builder) {
            if (auth()->check() && auth()->user()->hasAccount()) {
                $builder->where($builder->getModel()->getTable() . '.account_id', auth()->user()->account_id);
            }
        });

        // Automatically set account_id when creating
        static::creating(function ($model) {
            if (auth()->check() && auth()->user()->hasAccount() && empty($model->account_id)) {
                $model->account_id = auth()->user()->account_id;
            }
        });
    }

    /**
     * Scope a query to a specific account.
     */
    public function scopeForAccount(Builder $query, Account|int $account): Builder
    {
        $accountId = $account instanceof Account ? $account->id : $account;

        return $query->withoutGlobalScope('account')->where('account_id', $accountId);
    }
}
