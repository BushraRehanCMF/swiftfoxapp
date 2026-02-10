<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>WhatsApp OAuth Test</title>
    <style>
      body { font-family: Arial, sans-serif; background: #f5f7fb; color: #111827; }
      .wrap { max-width: 720px; margin: 40px auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; }
      .row { margin-bottom: 12px; }
      .label { font-size: 12px; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
      .value { font-size: 14px; word-break: break-all; }
      .btn { background: #16a34a; color: #fff; border: none; padding: 10px 14px; border-radius: 6px; cursor: pointer; }
      .btn:disabled { opacity: 0.6; cursor: not-allowed; }
      .box { border: 1px solid #e5e7eb; padding: 12px; border-radius: 6px; background: #f9fafb; }
      .error { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; padding: 10px; border-radius: 6px; }
      .success { color: #166534; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 10px; border-radius: 6px; }
      pre { white-space: pre-wrap; }
    </style>
  </head>
  <body>
    <div class="wrap">
      <h1>WhatsApp OAuth Test</h1>
      <p>This page uses the Meta JS SDK to open Embedded Signup and then posts the code to the server.</p>

      <div class="row">
        <div class="label">App ID</div>
        <div class="value">{{ $appId ?: 'Missing WHATSAPP_APP_ID' }}</div>
      </div>
      <div class="row">
        <div class="label">Config ID</div>
        <div class="value">{{ $configId ?: 'Missing WHATSAPP_CONFIG_ID' }}</div>
      </div>
      <div class="row">
        <div class="label">Redirect URI (server)</div>
        <div class="value">{{ $redirectUri }}</div>
      </div>

      @if ($error)
        <div class="row error">
          <strong>Error:</strong> {{ $error }}
        </div>
      @endif

      @if ($result)
        <div class="row success">
          <strong>Success:</strong> Connection data returned.
        </div>
        <div class="row box">
          <pre>{{ json_encode($result, JSON_PRETTY_PRINT) }}</pre>
        </div>
      @endif

      <div class="row">
        <button id="connect-btn" class="btn" type="button">Connect with Meta</button>
      </div>

      <form id="connect-form" method="POST" action="{{ route('whatsapp-test.connect') }}" style="display:none;">
        @csrf
        <input type="hidden" name="code" id="oauth-code" value="" />
        <input type="hidden" name="access_token" id="access-token" value="" />
        <input type="hidden" name="is_input_token" id="is-input-token" value="0" />
        <input type="hidden" name="redirect_uri" id="redirect-uri" value="" />
      </form>

      <div class="row box">
        <div class="label">Client Log</div>
        <pre id="client-log"></pre>
      </div>
    </div>

    <script>
      const appId = "{{ $appId }}";
      const connectBtn = document.getElementById('connect-btn');
      const logEl = document.getElementById('client-log');

      function log(message) {
        logEl.textContent += message + "\n";
      }

        function buildOauthUrl() {
          const redirectUri = window.location.href.split('#')[0].split('?')[0];
          const params = new URLSearchParams({
            client_id: appId,
            redirect_uri: redirectUri,
            response_type: 'code',
            scope: 'whatsapp_business_messaging,business_management',
            display: 'popup'
          });
          return `https://www.facebook.com/v22.0/dialog/oauth?${params.toString()}`;
        }

        function openOauthPopup() {
          const url = buildOauthUrl();
          log('Opening OAuth dialog...');
          log('Redirect URI: ' + window.location.href.split('#')[0].split('?')[0]);
          const popup = window.open(url, 'wa_oauth', 'width=650,height=720');
          if (!popup) {
            throw new Error('Popup blocked. Please allow popups and try again.');
          }

          const poll = window.setInterval(() => {
            try {
              if (popup.closed) {
                window.clearInterval(poll);
                connectBtn.disabled = false;
                log('Popup closed before completion.');
                return;
              }

              const popupUrl = popup.location.href;
              if (popupUrl && popupUrl.startsWith(window.location.origin)) {
                const urlObj = new URL(popupUrl);
                const code = urlObj.searchParams.get('code');
                const error = urlObj.searchParams.get('error');
                window.clearInterval(poll);
                popup.close();

                if (error) {
                  log('OAuth error: ' + error);
                  connectBtn.disabled = false;
                  return;
                }

                if (!code) {
                  log('No code received.');
                  connectBtn.disabled = false;
                  return;
                }

                document.getElementById('oauth-code').value = code;
                document.getElementById('access-token').value = '';
                document.getElementById('is-input-token').value = '0';
                document.getElementById('redirect-uri').value = window.location.href.split('#')[0].split('?')[0];

                log('Code received. Submitting to server...');
                document.getElementById('connect-form').submit();
              }
            } catch (e) {
              // Wait for redirect to same origin
            }
          }, 500);
        }

      connectBtn.addEventListener('click', () => {
        connectBtn.disabled = true;
        try {
          openOauthPopup();
        } catch (err) {
          log('Error: ' + (err.message || 'Unknown error'));
          connectBtn.disabled = false;
        }
      });
    </script>
