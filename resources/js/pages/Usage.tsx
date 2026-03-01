import React, { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';

type AccountUsage = {
  conversations_used: number;
  conversations_limit: number;
  conversations_remaining: number;
};

type AccountTrial = {
  is_on_trial: boolean;
  is_expired: boolean;
  ends_at?: string | null;
  days_remaining: number;
};

type AccountSubscription = {
  has_active_subscription: boolean;
  ends_at?: string | null;
  plan_name?: string;
};

type Account = {
  id: string;
  name: string;
  subscription_status: string;
  trial: AccountTrial;
  subscription?: AccountSubscription;
  usage: AccountUsage;
  whatsapp_connected: boolean;
  can_send_messages: boolean;
};

const Usage: React.FC = () => {
  const navigate = useNavigate();
  const [account, setAccount] = useState<Account | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [cancelling, setCancelling] = useState(false);
  const [canManageSubscription, setCanManageSubscription] = useState(false);

  const usagePercent = useMemo(() => {
    if (!account) return 0;
    if (account.usage.conversations_limit <= 0) return 0;
    return Math.min(100, Math.round((account.usage.conversations_used / account.usage.conversations_limit) * 100));
  }, [account]);

  const fetchAccount = async () => {
    setLoading(true);
    setError('');
    try {
      const { data } = await api.get('/auth/user');
      setAccount(data.data.account || null);
      setCanManageSubscription(data.data.role === 'owner');
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load usage data.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAccount();
  }, []);

  const handleCancelSubscription = async () => {
    const confirmed = window.confirm('Cancel at period end? Your subscription will stay active until the current billing period ends.');

    if (!confirmed) {
      return;
    }

    setCancelling(true);
    setError('');
    setNotice('');

    try {
      const { data } = await api.post('/checkout/cancel-subscription');
      setNotice(data.message || 'Subscription cancellation scheduled for period end.');
      await fetchAccount();
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Failed to cancel subscription.');
    } finally {
      setCancelling(false);
    }
  };

  const formatDate = (value?: string | null) => {
    if (!value) return 'Unknown';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return 'Unknown';
    return parsed.toLocaleDateString();
  };

  if (loading) {
    return <div className="text-sm text-gray-500">Loading usage...</div>;
  }

  if (error) {
    return <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>;
  }

  if (!account) {
    return <div className="text-sm text-gray-500">No account data available.</div>;
  }

  const isOnTrial = account.trial.is_on_trial;
  const hasActiveSubscription = account.subscription_status === 'active';
  const endDate = hasActiveSubscription ? account.subscription?.ends_at : account.trial.ends_at;
  const statusLabel = isOnTrial ? 'Trial Status' : 'Subscription Status';

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900">Usage & {isOnTrial ? 'Trial' : 'Subscription'}</h1>
        <p className="text-sm text-gray-600 mt-1">
          {isOnTrial
            ? 'Track trial status and conversation usage.'
            : 'Manage your subscription and monitor conversation usage.'}
        </p>
      </div>

      {notice && (
        <div className="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{notice}</div>
      )}

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <div className="text-xs uppercase tracking-wide text-gray-400">{statusLabel}</div>
          <div className="mt-2 text-lg font-semibold text-gray-900">
            {isOnTrial ? 'On trial' : account.subscription?.plan_name || 'Subscription active'}
          </div>
          <div className="mt-2 text-sm text-gray-600">
            {isOnTrial
              ? `${account.trial.days_remaining} days remaining`
              : hasActiveSubscription
                ? 'Active and billing monthly'
                : 'Subscription inactive'}
          </div>
          <div className="mt-4 text-xs text-gray-500">
            {isOnTrial ? 'Trial ends at: ' : 'Renews at: '}
            {formatDate(endDate)}
          </div>
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <div className="text-xs uppercase tracking-wide text-gray-400">Conversations</div>
          <div className="mt-2 flex items-end justify-between">
            <div className="text-2xl font-semibold text-gray-900">{account.usage.conversations_used}</div>
            <div className="text-sm text-gray-500">/ {account.usage.conversations_limit}</div>
          </div>
          <div className="mt-3 h-2 rounded-full bg-gray-100">
            <div className="h-2 rounded-full bg-emerald-600" style={{ width: `${usagePercent}%` }} />
          </div>
          <div className="mt-2 text-xs text-gray-500">{account.usage.conversations_remaining} remaining</div>
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <div className="text-xs uppercase tracking-wide text-gray-400">Messaging status</div>
          <div className="mt-2 text-lg font-semibold text-gray-900">
            {account.can_send_messages ? 'Sending enabled' : 'Sending disabled'}
          </div>
          <div className="mt-2 text-sm text-gray-600">
            {account.whatsapp_connected ? 'WhatsApp connected' : 'WhatsApp not connected'}
          </div>
          {!account.can_send_messages && (
            <div className="mt-4 text-xs text-amber-600">Upgrade required to resume sending.</div>
          )}
        </div>
      </div>

      {isOnTrial && (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-6 py-4 text-sm text-emerald-700">
          <div className="flex items-center justify-between">
            <span>Need more conversations? Upgrade your plan to continue using WhatsApp features after the trial.</span>
            <button
              onClick={() => navigate('/pricing')}
              className="ml-4 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition font-semibold"
            >
              View Plans
            </button>
          </div>
        </div>
      )}

      {hasActiveSubscription && canManageSubscription && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-6 py-4 text-sm text-red-700">
          <div className="flex items-center justify-between">
            <span>Need to stop billing? Cancel now and access remains active until your period end date.</span>
            <button
              onClick={handleCancelSubscription}
              disabled={cancelling}
              className="ml-4 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-semibold disabled:cursor-not-allowed disabled:opacity-60"
            >
              {cancelling ? 'Cancelling...' : 'Cancel Subscription'}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default Usage;
