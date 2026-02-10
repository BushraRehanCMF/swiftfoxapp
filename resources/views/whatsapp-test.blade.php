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
      const configId = "{{ $configId }}";
      const connectBtn = document.getElementById('connect-btn');
      const logEl = document.getElementById('client-log');

      function log(message) {
        logEl.textContent += message + "\n";
      }

      function loadFacebookSdk() {
        return new Promise((resolve, reject) => {
          if (window.FB) {
            resolve();
            return;
          }
          window.fbAsyncInit = function () {
            window.FB.init({
              appId: appId,
              cookie: true,
              xfbml: false,
              version: 'v18.0'
            });
            resolve();
          };
          const script = document.createElement('script');
          script.src = 'https://connect.facebook.net/en_US/sdk.js';
          script.async = true;
          script.defer = true;
          script.onerror = () => reject(new Error('Facebook SDK failed to load'));
          document.body.appendChild(script);
        });
      }

      connectBtn.addEventListener('click', async () => {
        log('Opening Embedded Signup...');
        connectBtn.disabled = true;

        try {
          await loadFacebookSdk();
          const dialogRedirectUri = window.location.href.split('#')[0];
          log('SDK loaded');
          log('Page URL: ' + window.location.href);
          log('Redirect URI sent to server: ' + dialogRedirectUri);

          window.FB.login((response) => {
            log('FB.login response received');
            const authData = response && response.authResponse ? response.authResponse : null;
            const inputToken = authData ? authData.input_token : null;
            const code = authData ? authData.code : null;

            log('input_token present: ' + (!!inputToken));
            log('code present: ' + (!!code));

            if (!inputToken && !code) {
              log('No authorization token received.');
              connectBtn.disabled = false;
              return;
            }

            const token = inputToken || code;
            document.getElementById('oauth-code').value = token;
            document.getElementById('is-input-token').value = inputToken ? '1' : '0';
            document.getElementById('redirect-uri').value = dialogRedirectUri;

            log('Submitting to server...');
            document.getElementById('connect-form').submit();
          }, {
            config_id: configId,
            response_type: 'code',
            override_default_response_type: true,
            scope: 'whatsapp_business_messaging,business_management'
          });
        } catch (err) {
          log('Error: ' + (err.message || 'Unknown error'));
          connectBtn.disabled = false;
        }
      });
    </script>
  </body>
</html>
