import React, { useEffect, useMemo, useRef, useState } from 'react';
import api from '../services/api';

type ConnectionStatus = {
  connected: boolean;
  phone_number?: string | null;
  phone_number_id?: string | null;
  waba_id?: string | null;
  status?: string | null;
  connected_at?: string | null;
};

type EmbeddedConfig = {
  app_id?: string | null;
  config_id?: string | null;
};

declare global {
  interface Window {
    FB?: {
      init: (options: { appId: string; autoLogAppEvents?: boolean; xfbml?: boolean; version: string }) => void;
      login: (callback: (response: any) => void, options: Record<string, unknown>) => void;
    };
    fbAsyncInit?: () => void;
  }
}

const WhatsApp: React.FC = () => {
  const [status, setStatus] = useState<ConnectionStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [connecting, setConnecting] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [config, setConfig] = useState<EmbeddedConfig | null>(null);

  // Refs to capture session info from Embedded Signup postMessage events
  const capturedWabaId = useRef<string | null>(null);
  const capturedPhoneNumberId = useRef<string | null>(null);

  const hasConfig = useMemo(() => Boolean(config?.app_id && config?.config_id), [config]);

  const fetchStatus = async () => {
    setLoading(true);
    setError('');
    try {
      const { data } = await api.get('/whatsapp/status');
      setStatus(data.data);
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load WhatsApp status.');
    } finally {
      setLoading(false);
    }
  };

  const fetchConfig = async () => {
    try {
      const { data } = await api.get('/whatsapp/config');
      setConfig(data.data || null);
      return data.data || null;
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to load Embedded Signup configuration.');
      return null;
    }
  };

  const ensureFacebookSdk = (appId: string) =>
    new Promise<void>((resolve, reject) => {
      if (window.FB) {
        window.FB.init({ appId, autoLogAppEvents: true, xfbml: true, version: 'v22.0' });
        resolve();
        return;
      }

      const existing = document.getElementById('facebook-jssdk');
      if (existing) {
        const checkReady = window.setInterval(() => {
          if (window.FB) {
            window.clearInterval(checkReady);
            window.FB.init({ appId, autoLogAppEvents: true, xfbml: true, version: 'v22.0' });
            resolve();
          }
        }, 50);
        window.setTimeout(() => {
          window.clearInterval(checkReady);
          reject(new Error('Facebook SDK failed to load.'));
        }, 10000);
        return;
      }

      window.fbAsyncInit = () => {
        if (!window.FB) {
          reject(new Error('Facebook SDK failed to initialize.'));
          return;
        }
        window.FB.init({ appId, autoLogAppEvents: true, xfbml: true, version: 'v22.0' });
        resolve();
      };

      const script = document.createElement('script');
      script.id = 'facebook-jssdk';
      script.async = true;
      script.defer = true;
      script.src = 'https://connect.facebook.net/en_US/sdk.js';
      script.onerror = () => reject(new Error('Facebook SDK failed to load.'));
      document.body.appendChild(script);
    });

  // Listen for WA_EMBEDDED_SIGNUP postMessage events from Facebook
  useEffect(() => {
    const handler = (event: MessageEvent) => {
      if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') return;
      try {
        const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
        if (data.type === 'WA_EMBEDDED_SIGNUP') {
          console.log('📩 WA_EMBEDDED_SIGNUP event received', data.data);
          capturedWabaId.current = data.data?.waba_id || null;
          capturedPhoneNumberId.current = data.data?.phone_number_id || null;
        }
      } catch {
        // Not a JSON message, ignore
      }
    };
    window.addEventListener('message', handler);
    return () => window.removeEventListener('message', handler);
  }, []);

  const startEmbeddedSignup = async () => {
    setError('');
    setNotice('');
    setConnecting(true);

    const currentConfig = config ?? (await fetchConfig());
    if (!currentConfig?.app_id || !currentConfig?.config_id) {
      setError('Missing Meta configuration. Set WHATSAPP_APP_ID and WHATSAPP_CONFIG_ID in your .env.');
      setConnecting(false);
      return;
    }

    try {
      await ensureFacebookSdk(currentConfig.app_id);
    } catch (err: any) {
      setError(err.message || 'Failed to load Facebook SDK.');
      setConnecting(false);
      return;
    }

    // Reset captured session info
    capturedWabaId.current = null;
    capturedPhoneNumberId.current = null;

    setNotice('Completing signup in the popup window...');

    try {
      window.FB!.login(
        (response: any) => {
          console.log('FB.login callback received', response);

          if (!response.authResponse) {
            setConnecting(false);
            setNotice('');
            setError('Login cancelled or failed.');
            return;
          }

          const code = response.authResponse.code || '';
          const accessToken = response.authResponse.accessToken || '';

          console.log('Has code:', !!code);
          console.log('Has accessToken:', !!accessToken);
          console.log('Captured waba_id:', capturedWabaId.current);
          console.log('Captured phone_number_id:', capturedPhoneNumberId.current);

          if (!capturedWabaId.current || !capturedPhoneNumberId.current) {
            setConnecting(false);
            setNotice('');
            setError('Embedded Signup did not return WABA or phone number info. Please try again.');
            return;
          }

          console.log('📤 Sending to /whatsapp/connect...');
          api.post('/whatsapp/connect', {
            code: code || undefined,
            access_token: accessToken || undefined,
            waba_id: capturedWabaId.current,
            phone_number_id: capturedPhoneNumberId.current,
          })
            .then((connectResponse: any) => {
              console.log('✅ WhatsApp connection successful:', connectResponse.data);
              setNotice('WhatsApp connected successfully!');
              fetchStatus();
            })
            .catch((err: any) => {
              console.error('❌ Connection failed:', err.response?.data || err.message);
              setError(err.response?.data?.error?.message || 'Failed to connect WhatsApp.');
            })
            .finally(() => {
              setConnecting(false);
            });
        },
        {
          config_id: currentConfig.config_id,
          response_type: 'code',
          override_default_response_type: true,
          extras: {
            setup: {},
            featureType: '',
            sessionInfoVersion: '3',
          },
        }
      );
    } catch (err: any) {
      setError(err.message || 'Unable to start Embedded Signup.');
      setConnecting(false);
    }
  };

  const disconnect = async () => {
    setError('');
    setNotice('');
    try {
      await api.post('/whatsapp/disconnect');
      await fetchStatus();
      setNotice('WhatsApp disconnected.');
    } catch (err: any) {
      setError(err.response?.data?.error?.message || 'Unable to disconnect WhatsApp.');
    }
  };

  useEffect(() => {
    fetchStatus();
    fetchConfig();
  }, []);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold text-gray-900">WhatsApp Connection</h1>
        <p className="text-sm text-gray-600 mt-1">
          Connect your WhatsApp Business number through Meta Embedded Signup.
        </p>
      </div>

      {error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
      {notice && <div className="rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{notice}</div>}

      <div className="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        {loading ? (
          <div className="text-sm text-gray-500">Loading connection status...</div>
        ) : status?.connected ? (
          <div className="space-y-4">
            <div>
              <div className="text-sm text-gray-500">Status</div>
              <div className="text-lg font-semibold text-emerald-700">Connected</div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <div className="text-xs uppercase tracking-wide text-gray-400">Phone Number</div>
                <div className="text-sm text-gray-900">{status.phone_number || 'Unknown'}</div>
              </div>
              <div>
                <div className="text-xs uppercase tracking-wide text-gray-400">WABA ID</div>
                <div className="text-sm text-gray-900">{status.waba_id || 'Unknown'}</div>
              </div>
              <div>
                <div className="text-xs uppercase tracking-wide text-gray-400">Phone Number ID</div>
                <div className="text-sm text-gray-900">{status.phone_number_id || 'Unknown'}</div>
              </div>
              <div>
                <div className="text-xs uppercase tracking-wide text-gray-400">Connected At</div>
                <div className="text-sm text-gray-900">{status.connected_at ? new Date(status.connected_at).toLocaleString() : 'Unknown'}</div>
              </div>
            </div>
            <button
              type="button"
              onClick={disconnect}
              className="inline-flex items-center rounded border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
            >
              Disconnect WhatsApp
            </button>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="text-sm text-gray-600">
              No WhatsApp number is connected yet. This is required to send and receive messages.
            </div>
            <div className="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
              You must complete Meta Embedded Signup. Direct manual connections are disabled in V1.
            </div>
            <div className="flex flex-wrap gap-3">
              <button
                type="button"
                onClick={startEmbeddedSignup}
                disabled={!hasConfig || connecting}
                className="inline-flex items-center rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-emerald-300"
              >
                {connecting ? 'Starting signup...' : 'Connect with Meta'}
              </button>
              {!hasConfig && (
                <div className="text-xs text-gray-500 flex items-center">
                  Set WHATSAPP_APP_ID and WHATSAPP_CONFIG_ID to enable signup.
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default WhatsApp;
