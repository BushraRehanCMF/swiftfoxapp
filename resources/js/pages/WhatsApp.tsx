import React, { useEffect, useMemo, useState } from 'react';
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
      init: (options: { appId: string; xfbml?: boolean; version: string }) => void;
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
        window.FB.init({ appId, xfbml: false, version: 'v18.0' });
        resolve();
        return;
      }

      const existing = document.getElementById('facebook-jssdk');
      if (existing) {
        const checkReady = window.setInterval(() => {
          if (window.FB) {
            window.clearInterval(checkReady);
            window.FB.init({ appId, xfbml: false, version: 'v18.0' });
            resolve();
          }
        }, 50);
        window.setTimeout(() => {
          window.clearInterval(checkReady);
          reject(new Error('Facebook SDK failed to load.'));
        }, 5000);
        return;
      }

      window.fbAsyncInit = () => {
        if (!window.FB) {
          reject(new Error('Facebook SDK failed to initialize.'));
          return;
        }
        window.FB.init({ appId, xfbml: false, version: 'v18.0' });
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

      // Handle FB.login response - this is where the authorization comes back
      const handleFBLoginResponse = (response: any) => {
        // Use IIFE to handle async code
        (async () => {
          console.log('📥 FB.login callback received');
          console.log('Full response:', response);
          console.log('Response keys:', Object.keys(response || {}));

          // The response structure for Embedded Signup is: { authResponse: {...}, status: 'connected' }
          const authData = response?.authResponse;
          console.log('authResponse:', authData);
          console.log('authResponse keys:', Object.keys(authData || {}));

          // Prefer access token flow for Embedded Signup
          const accessToken = authData?.accessToken;
          // Check for input_token (JWT from Embedded Signup modal)
          const inputToken = authData?.input_token;
          // OR check for authorization code
          const code = authData?.code;

          console.log('access_token:', accessToken ? accessToken.substring(0, 30) + '...' : 'NOT FOUND');
          console.log('input_token:', inputToken ? inputToken.substring(0, 30) + '...' : 'NOT FOUND');
          console.log('authorization code:', code ? code.substring(0, 30) + '...' : 'NOT FOUND');

          if (!accessToken && !inputToken && !code) {
            console.error('❌ No authorization data in FB.login response', {
              has_response: !!response,
              has_authResponse: !!authData,
              authResponse_keys: Object.keys(authData || {}),
            });
            setError('Login failed. No authorization token received. Check console for details.');
            setConnecting(false);
            return;
          }

          console.log('✅ Authorization data received');

          try {
            // Send the token to backend to exchange for WABA info
            console.log('📤 Sending authorization data to /whatsapp/connect...');
            const connectResponse = await api.post('/whatsapp/connect', {
              access_token: accessToken,
              code: accessToken ? undefined : (inputToken || code),
              is_input_token: !!inputToken,
            });

            console.log('✅ WhatsApp connection successful:', connectResponse.data);
            setNotice('WhatsApp connected successfully!');
            await fetchStatus();
          } catch (err: any) {
            console.error('❌ Connection failed:', err.response?.data || err.message);
            setError(
              err.response?.data?.error?.message || 'Failed to connect WhatsApp.'
            );
          } finally {
            setConnecting(false);
          }
        })();
      };

      const expectedRedirectUri = `${window.location.origin}/whatsapp`;
      console.log('Redirect URI for OAuth (page URL):', window.location.href);
      console.log('Expected redirect URI:', expectedRedirectUri);

      // Use FB.login with Embedded Signup config
      // The response comes back in the callback with accessToken
      window.FB?.login(handleFBLoginResponse, {
        config_id: currentConfig.config_id,
        response_type: 'code',
        override_default_response_type: true,
        scope: 'whatsapp_business_messaging,business_management',
      });

      setNotice('Completing signup in the popup window...');
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
