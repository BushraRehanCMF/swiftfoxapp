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
        <input type="hidden" name="waba_id" id="waba-id" value="" />
        <input type="hidden" name="phone_number_id" id="phone-number-id" value="" />
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

      // Load Facebook JS SDK
      window.fbAsyncInit = function() {
        FB.init({
          appId: appId,
          autoLogAppEvents: true,
          xfbml: true,
          version: 'v22.0'
        });
        log('Facebook SDK initialized.');
      };

      (function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) return;
        js = d.createElement(s); js.id = id;
        js.src = "https://connect.facebook.net/en_US/sdk.js";
        fjs.parentNode.insertBefore(js, fjs);
      }(document, 'script', 'facebook-jssdk'));

      // Session info listener for Embedded Signup
      let capturedWabaId = null;
      let capturedPhoneNumberId = null;

      window.addEventListener('message', function(event) {
        if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') return;
        try {
          const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
          if (data.type === 'WA_EMBEDDED_SIGNUP') {
            log('📩 WA_EMBEDDED_SIGNUP event received');
            log('  waba_id: ' + (data.data?.waba_id || 'N/A'));
            log('  phone_number_id: ' + (data.data?.phone_number_id || 'N/A'));
            log('  current_step: ' + (data.data?.current_step || 'N/A'));
            capturedWabaId = data.data?.waba_id || null;
            capturedPhoneNumberId = data.data?.phone_number_id || null;
          }
        } catch (e) {
          // Not a JSON message, ignore
        }
      });

      connectBtn.addEventListener('click', () => {
        connectBtn.disabled = true;
        capturedWabaId = null;
        capturedPhoneNumberId = null;

        if (typeof FB === 'undefined') {
          log('Error: Facebook SDK not loaded yet. Please wait and try again.');
          connectBtn.disabled = false;
          return;
        }

        log('Opening Embedded Signup via FB.login...');
        log('config_id: ' + configId);

        FB.login(function(response) {
          log('FB.login callback received.');
          log('Status: ' + response.status);

          if (response.authResponse) {
            const code = response.authResponse.code || '';
            const accessToken = response.authResponse.accessToken || '';

            log('Has code: ' + !!code);
            log('Has accessToken: ' + !!accessToken);
            log('Captured waba_id: ' + (capturedWabaId || 'N/A'));
            log('Captured phone_number_id: ' + (capturedPhoneNumberId || 'N/A'));

            if (!capturedWabaId || !capturedPhoneNumberId) {
              log('⚠️  Missing waba_id or phone_number_id from session info. Cannot proceed.');
              connectBtn.disabled = false;
              return;
            }

            document.getElementById('oauth-code').value = code;
            document.getElementById('access-token').value = accessToken;
            document.getElementById('waba-id').value = capturedWabaId;
            document.getElementById('phone-number-id').value = capturedPhoneNumberId;

            log('Submitting to server...');
            document.getElementById('connect-form').submit();
          } else {
            log('Login cancelled or failed.');
            connectBtn.disabled = false;
          }
        }, {
          config_id: configId,
          response_type: 'code',
          override_default_response_type: true,
          extras: {
            setup: {},
            featureType: '',
            sessionInfoVersion: '3',
          }
        });
      });
    </script>
