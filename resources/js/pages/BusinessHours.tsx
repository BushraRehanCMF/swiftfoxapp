import React, { useEffect, useMemo, useState } from 'react';
import api from '../services/api';

type BusinessHour = {
  day_of_week: number;
  day_name: string;
  start_time: string;
  end_time: string;
  is_enabled: boolean;
};

type BusinessHoursPayload = {
  timezone: string;
  hours: BusinessHour[];
};

type StatusCheck = {
  is_open: boolean;
  timezone: string;
  current_time: string;
  current_day: number;
};

const BusinessHours: React.FC = () => {
  const [timezone, setTimezone] = useState('UTC');
  const [hours, setHours] = useState<BusinessHour[]>([]);
  const [status, setStatus] = useState<StatusCheck | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const sortedHours = useMemo(() => [...hours].sort((a, b) => a.day_of_week - b.day_of_week), [hours]);

  const fetchBusinessHours = async () => {
    setLoading(true);
    setError('');
    try {
      const { data } = await api.get('/business-hours');
      const payload = data.data as BusinessHoursPayload;
      setTimezone(payload.timezone || 'UTC');
      setHours(
        (payload.hours || []).map(hour => ({
          ...hour,
          start_time: normalizeTime(hour.start_time),
          end_time: normalizeTime(hour.end_time),
        }))
      );
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load business hours.');
    } finally {
      setLoading(false);
    }
  };

  const fetchStatus = async () => {
    try {
      const { data } = await api.get('/business-hours/check');
      setStatus(data.data);
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to check status.');
    }
  };

  useEffect(() => {
    fetchBusinessHours();
    fetchStatus();
  }, []);

  const updateHour = (dayOfWeek: number, updates: Partial<BusinessHour>) => {
    setHours(prev =>
      prev.map(hour => (hour.day_of_week === dayOfWeek ? { ...hour, ...updates } : hour))
    );
  };

  const toggleAll = (enabled: boolean) => {
    setHours(prev => prev.map(hour => ({ ...hour, is_enabled: enabled })));
  };

  const save = async () => {
    setSaving(true);
    setError('');
    setNotice('');

    try {
      await api.put('/business-hours', {
        timezone,
        hours: sortedHours.map(hour => ({
          day_of_week: hour.day_of_week,
          start_time: normalizeTime(hour.start_time),
          end_time: normalizeTime(hour.end_time),
          is_enabled: hour.is_enabled,
        })),
      });
      setNotice('Business hours updated successfully.');
      await fetchBusinessHours();
      await fetchStatus();
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to save business hours.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <div className="text-sm text-gray-500">Loading business hours...</div>;
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900">Business Hours</h1>
        <p className="text-sm text-gray-600 mt-1">Set when your team is available for automations.</p>
      </div>

      {error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {notice && <div className="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{notice}</div>}

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <div className="text-sm font-semibold text-gray-700">Weekly hours</div>
              <div className="text-xs text-gray-500">Times are saved in your account timezone.</div>
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => toggleAll(true)}
                className="rounded border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
              >
                Enable all
              </button>
              <button
                type="button"
                onClick={() => toggleAll(false)}
                className="rounded border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
              >
                Disable all
              </button>
            </div>
          </div>

          <div className="mt-4 space-y-3">
            {sortedHours.map(hour => (
              <div key={hour.day_of_week} className="grid gap-3 sm:grid-cols-[140px_1fr_1fr_auto] sm:items-center">
                <div className="text-sm font-medium text-gray-700">{hour.day_name}</div>
                <input
                  type="time"
                  value={hour.start_time}
                  onChange={event => updateHour(hour.day_of_week, { start_time: event.target.value })}
                  className="rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
                />
                <input
                  type="time"
                  value={hour.end_time}
                  onChange={event => updateHour(hour.day_of_week, { end_time: event.target.value })}
                  className="rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
                />
                <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                  <input
                    type="checkbox"
                    checked={hour.is_enabled}
                    onChange={event => updateHour(hour.day_of_week, { is_enabled: event.target.checked })}
                    className="h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600"
                  />
                  Enabled
                </label>
              </div>
            ))}
          </div>

          <div className="mt-6">
            <label className="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
            <input
              type="text"
              value={timezone}
              onChange={event => setTimezone(event.target.value)}
              className="w-full max-w-sm rounded border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600"
              placeholder="e.g. UTC"
            />
          </div>

          <div className="mt-6 flex flex-wrap gap-3">
            <button
              type="button"
              onClick={save}
              disabled={saving}
              className="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
            >
              {saving ? 'Saving...' : 'Save hours'}
            </button>
            <button
              type="button"
              onClick={fetchBusinessHours}
              className="inline-flex items-center rounded border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Reset
            </button>
          </div>
        </div>

        <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
          <div className="text-sm font-semibold text-gray-700">Current status</div>
          {status ? (
            <div className="mt-4 space-y-3 text-sm text-gray-600">
              <div>
                <div className="text-xs uppercase tracking-wide text-gray-400">Status</div>
                <div className={status.is_open ? 'text-emerald-700 font-semibold' : 'text-amber-600 font-semibold'}>
                  {status.is_open ? 'Open now' : 'Closed now'}
                </div>
              </div>
              <div>
                <div className="text-xs uppercase tracking-wide text-gray-400">Timezone</div>
                <div>{status.timezone}</div>
              </div>
              <div>
                <div className="text-xs uppercase tracking-wide text-gray-400">Current time</div>
                <div>{status.current_time}</div>
              </div>
            </div>
          ) : (
            <div className="mt-4 text-sm text-gray-500">Status unavailable.</div>
          )}
          <button
            type="button"
            onClick={fetchStatus}
            className="mt-4 inline-flex items-center rounded border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
          >
            Refresh status
          </button>
        </div>
      </div>
    </div>
  );
};

const normalizeTime = (value: string) => {
  if (!value) return '09:00';
  return value.length >= 5 ? value.slice(0, 5) : value;
};

export default BusinessHours;
