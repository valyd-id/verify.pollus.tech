<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Webhook Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('webhook-settings.update') }}">
                        @csrf
                        @method('PUT')

                        <!-- Webhook URL -->
                        <div class="mb-6">
                            <label for="webhook_url" class="block text-sm font-medium text-gray-700 mb-2">
                                Webhook URL
                            </label>
                            <input 
                                type="url" 
                                id="webhook_url" 
                                name="webhook_url" 
                                value="{{ old('webhook_url', $company->webhook_url) }}" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://your-domain.com/webhook"
                            >
                            @error('webhook_url')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">The URL where verification results will be posted.</p>
                        </div>

                        <!-- Provider Down Webhook URL -->
                        <div class="mb-6">
                            <label for="provider_down_webhook_url" class="block text-sm font-medium text-gray-700 mb-2">
                                Provider Down Webhook URL
                            </label>
                            <input 
                                type="url" 
                                id="provider_down_webhook_url" 
                                name="provider_down_webhook_url" 
                                value="{{ old('provider_down_webhook_url', $company->provider_down_webhook_url) }}" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://your-domain.com/api/provider-status"
                            >
                            @error('provider_down_webhook_url')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">The URL where cert org status updates (provider up/down) will be posted. Uses the same Auth0 credentials configured below.</p>
                        </div>

                        <!-- Webhook Enabled -->
                        <div class="mb-6">
                            <label class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    name="webhook_enabled" 
                                    value="1"
                                    {{ old('webhook_enabled', $company->webhook_enabled) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                >
                                <span class="ml-2 text-sm text-gray-700">Enable webhook delivery</span>
                            </label>
                        </div>

                        <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 p-4">
                            <label class="flex items-start gap-2">
                                <input
                                    type="checkbox"
                                    name="hold_not_found_production_webhooks"
                                    value="1"
                                    {{ old('hold_not_found_production_webhooks', $company->hold_not_found_production_webhooks ?? false) ? 'checked' : '' }}
                                    class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                >
                                <span>
                                    <span class="block text-sm font-medium text-gray-900">{{ __('Hold not_found production webhooks') }}</span>
                                    <span class="block text-xs text-gray-600 mt-1">
                                        {{ __('When enabled, terminal “not found” results do not post to your production MedEdge URL until you send them from the dashboard. Test webhooks still fire immediately. This does not affect VC manual review (manual_check) — that flow stays separate.') }}
                                    </span>
                                </span>
                            </label>
                        </div>

                        <hr class="my-8">

                        <!-- Webhook Authentication Method -->
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Webhook Authentication</h3>
                        <p class="text-sm text-gray-600 mb-4">Choose how to authenticate webhook requests to your endpoint.</p>

                        <div class="mb-6">
                            <label for="webhook_auth_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Authentication Method
                            </label>
                            <select 
                                id="webhook_auth_type" 
                                name="webhook_auth_type"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                onchange="toggleAuthFields()"
                            >
                                <option value="none" {{ old('webhook_auth_type', $company->webhook_auth_type ?? 'none') === 'none' ? 'selected' : '' }}>No Authentication</option>
                                <option value="api_key" {{ old('webhook_auth_type', $company->webhook_auth_type) === 'api_key' ? 'selected' : '' }}>API Key (Header)</option>
                                <option value="auth0" {{ old('webhook_auth_type', $company->webhook_auth_type) === 'auth0' ? 'selected' : '' }}>Auth0 Bearer Token</option>
                            </select>
                            @error('webhook_auth_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Select the authentication method your webhook endpoint requires.</p>
                        </div>

                        <!-- API Key Authentication Fields -->
                        <div id="api_key_fields" style="display: {{ old('webhook_auth_type', $company->webhook_auth_type) === 'api_key' ? 'block' : 'none' }};">
                            <div class="mb-6">
                                <label for="webhook_api_key" class="block text-sm font-medium text-gray-700 mb-2">
                                    API Key
                                </label>
                                <input 
                                    type="password" 
                                    id="webhook_api_key" 
                                    name="webhook_api_key" 
                                    value="{{ old('webhook_api_key', $company->webhook_api_key) }}" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="Your webhook API key"
                                >
                                @error('webhook_api_key')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">The API key to send in the header for webhook authentication.</p>
                            </div>

                            <div class="mb-6">
                                <label for="webhook_auth_header_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Header Name
                                </label>
                                <input 
                                    type="text" 
                                    id="webhook_auth_header_name" 
                                    name="webhook_auth_header_name" 
                                    value="{{ old('webhook_auth_header_name', $company->webhook_auth_header_name ?? 'X-API-Key') }}" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="X-API-Key"
                                >
                                @error('webhook_auth_header_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Header name to use for API key (e.g., "X-API-Key" or "Authorization"). If "Authorization", will use "Bearer {key}" format.</p>
                            </div>
                        </div>

                        <!-- Auth0 Authentication Fields -->
                        <div id="auth0_fields" style="display: {{ old('webhook_auth_type', $company->webhook_auth_type) === 'auth0' ? 'block' : 'none' }};">
                            <h4 class="font-semibold text-gray-800 mb-3">Auth0 Machine-to-Machine Authentication</h4>
                            <p class="text-sm text-gray-600 mb-4">Configure Auth0 credentials for webhook authentication. Tokens are cached and reused until near expiration.</p>

                            <div class="mb-6">
                                <label for="auth0_domain" class="block text-sm font-medium text-gray-700 mb-2">
                                    Auth0 Domain
                                </label>
                                <input 
                                    type="text" 
                                    id="auth0_domain" 
                                    name="auth0_domain" 
                                    value="{{ old('auth0_domain', $company->auth0_domain) }}" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="your-tenant.auth0.com"
                                >
                                @error('auth0_domain')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-6">
                                <label for="auth0_client_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Auth0 Client ID
                                </label>
                                <input 
                                    type="text" 
                                    id="auth0_client_id" 
                                    name="auth0_client_id" 
                                    value="{{ old('auth0_client_id', $company->auth0_client_id) }}" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="Your Auth0 Client ID"
                                >
                                @error('auth0_client_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-6">
                                <label for="auth0_client_secret" class="block text-sm font-medium text-gray-700 mb-2">
                                    Auth0 Client Secret
                                </label>
                                <input 
                                    type="password" 
                                    id="auth0_client_secret" 
                                    name="auth0_client_secret" 
                                    value="{{ old('auth0_client_secret', $company->auth0_client_secret) }}" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="Your Auth0 Client Secret"
                                >
                                @error('auth0_client_secret')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-6">
                                <label for="auth0_audience" class="block text-sm font-medium text-gray-700 mb-2">
                                    Auth0 Audience
                                </label>
                                <input 
                                    type="text" 
                                    id="auth0_audience" 
                                    name="auth0_audience" 
                                    value="{{ old('auth0_audience', $company->auth0_audience) }}" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                    placeholder="https://your-api-identifier"
                                >
                                @error('auth0_audience')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Commented out: Auth0 Settings
                        <hr class="my-8">

                        <!-- Auth0 Settings -->
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Auth0 Machine-to-Machine Authentication</h3>
                        <p class="text-sm text-gray-600 mb-4">Configure Auth0 credentials for webhook authentication. Tokens are cached and reused until near expiration.</p>

                        <div class="mb-6">
                            <label for="auth0_domain" class="block text-sm font-medium text-gray-700 mb-2">
                                Auth0 Domain
                            </label>
                            <input 
                                type="text" 
                                id="auth0_domain" 
                                name="auth0_domain" 
                                value="{{ old('auth0_domain', $company->auth0_domain) }}" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="your-tenant.auth0.com"
                            >
                            @error('auth0_domain')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="auth0_client_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Auth0 Client ID
                            </label>
                            <input 
                                type="text" 
                                id="auth0_client_id" 
                                name="auth0_client_id" 
                                value="{{ old('auth0_client_id', $company->auth0_client_id) }}" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Your Auth0 Client ID"
                            >
                            @error('auth0_client_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="auth0_client_secret" class="block text-sm font-medium text-gray-700 mb-2">
                                Auth0 Client Secret
                            </label>
                            <input 
                                type="password" 
                                id="auth0_client_secret" 
                                name="auth0_client_secret" 
                                value="{{ old('auth0_client_secret', $company->auth0_client_secret) }}" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Your Auth0 Client Secret"
                            >
                            @error('auth0_client_secret')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="auth0_audience" class="block text-sm font-medium text-gray-700 mb-2">
                                Auth0 Audience
                            </label>
                            <input 
                                type="text" 
                                id="auth0_audience" 
                                name="auth0_audience" 
                                value="{{ old('auth0_audience', $company->auth0_audience) }}" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="https://your-api-identifier"
                            >
                            @error('auth0_audience')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <hr class="my-8">

                        <!-- NPPES Retry Settings -->
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">NPPES Retry Settings</h3>

                        <div class="mb-6">
                            <label for="nppes_retry_max" class="block text-sm font-medium text-gray-700 mb-2">
                                Maximum NPPES Retries
                            </label>
                            <input 
                                type="number" 
                                id="nppes_retry_max" 
                                name="nppes_retry_max" 
                                value="{{ old('nppes_retry_max', $company->nppes_retry_max) }}" 
                                min="0"
                                max="20"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                            >
                            @error('nppes_retry_max')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">How many times to retry when provider returns NPPES fallback (default: 5).</p>
                        </div>

                        <div class="mb-6">
                            <label for="nppes_retry_interval_hours" class="block text-sm font-medium text-gray-700 mb-2">
                                NPPES Retry Interval (hours)
                            </label>
                            <input 
                                type="number" 
                                id="nppes_retry_interval_hours" 
                                name="nppes_retry_interval_hours" 
                                value="{{ old('nppes_retry_interval_hours', $company->nppes_retry_interval_hours) }}" 
                                min="1"
                                max="168"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                            >
                            @error('nppes_retry_interval_hours')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Hours between NPPES retries (default: 6).</p>
                        </div>

                        <div class="mb-6">
                            <label for="nppes_alert_email" class="block text-sm font-medium text-gray-700 mb-2">
                                NPPES Alert Email
                            </label>
                            <input 
                                type="email" 
                                id="nppes_alert_email" 
                                name="nppes_alert_email" 
                                value="{{ old('nppes_alert_email', $company->nppes_alert_email) }}" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="admin@company.com"
                            >
                            @error('nppes_alert_email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">⚠️ Email address to receive alerts when max NPPES retry attempts are reached (NPPES verifications are NOT shown in the system and NO webhooks are sent).</p>
                        </div>

                        <hr class="my-8">
                        --}}

                        <!-- Monthly Re-verification Settings -->
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Re-verification Settings</h3>

                        <div class="mb-6">
                            <label class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    name="monthly_reverify_enabled" 
                                    value="1"
                                    {{ old('monthly_reverify_enabled', $company->monthly_reverify_enabled) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                >
                                <span class="ml-2 text-sm text-gray-700">Enable monthly re-verification</span>
                            </label>
                        </div>

                        <div class="mb-6">
                            <label for="monthly_reverify_interval_days" class="block text-sm font-medium text-gray-700 mb-2">
                                Re-verification Interval (days)
                            </label>
                            <input 
                                type="number" 
                                id="monthly_reverify_interval_days" 
                                name="monthly_reverify_interval_days" 
                                value="{{ old('monthly_reverify_interval_days', $company->monthly_reverify_interval_days) }}" 
                                min="1"
                                max="365"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                            >
                            @error('monthly_reverify_interval_days')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Days between automatic re-verifications (default: 30).</p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-end mt-8">
                            <button 
                                type="submit" 
                                class="px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            >
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MedEdge Webhook Format Info -->
            <div class="bg-blue-50 overflow-hidden shadow-sm sm:rounded-lg mt-8">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-blue-900 mb-4">MedEdge Webhook Format</h3>
                    <p class="text-sm text-blue-800 mb-3">Webhooks are sent in MedEdge format with Auth0 bearer token authentication:</p>
                    <pre class="bg-white p-4 rounded border border-blue-200 text-xs overflow-x-auto"><code>{
  "success": true,
  "Request_ID": "550e8400-e29b-41d4-a716-446655440000",
  "Response_msg": "Jane Doe Not Expired - [RN] expiring [12/31/2026]...",
  "Status": "1",
  "Last_activity": "1/30/2026 5:45:32 PM",
  "Issued_to": "Jane Doe",
  "Expiration_date": "12/31/2026",
  "Certification_name": "RN",
  "Provider": "California: Board of Registered Nursing",
  "Status_Notes": "",
  "Client_Request_ID": "",
  "Screenshot": "data:image/jpg;base64,..."
}</code></pre>
                    <div class="mt-4 text-sm text-blue-800">
                        <p class="font-semibold mb-2">Status Codes:</p>
                        <ul class="list-disc list-inside space-y-1 ml-2">
                            <li><strong>"1"</strong> - Verified and not expired</li>
                            <li><strong>"2"</strong> - Verified but expired</li>
                            <li><strong>"16"</strong> - Verification failed</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleAuthFields() {
            const authType = document.getElementById('webhook_auth_type').value;
            const apiKeyFields = document.getElementById('api_key_fields');
            const auth0Fields = document.getElementById('auth0_fields');

            if (authType === 'api_key') {
                apiKeyFields.style.display = 'block';
                auth0Fields.style.display = 'none';
            } else if (authType === 'auth0') {
                apiKeyFields.style.display = 'none';
                auth0Fields.style.display = 'block';
            } else {
                apiKeyFields.style.display = 'none';
                auth0Fields.style.display = 'none';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleAuthFields();
        });
    </script>
</x-app-layout>
