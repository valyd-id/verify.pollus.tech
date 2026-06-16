<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('API Documentation') }}
            </h2>
            <a href="{{ route('api-docs.postman-download') }}" 
               class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-semibold rounded-lg shadow-md transition duration-150 ease-in-out">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
                Export Postman Collection
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Quick Start with Postman -->
            <div class="bg-gradient-to-r from-orange-50 to-orange-100 border-l-4 border-orange-500 shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-lg font-semibold text-orange-900">Quick Start with Postman</h3>
                            <div class="mt-2 text-sm text-orange-800">
                                <ol class="list-decimal list-inside space-y-1">
                                    <li>Click the "Export Postman Collection" button above</li>
                                    <li>Open Postman and click "Import"</li>
                                    <li>Select the downloaded <code class="bg-orange-200 px-1 rounded">Valyd_API_Collection.json</code> file</li>
                                    <li>All endpoints are ready to use with your API key pre-configured! ✅</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- API Configuration -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Your API Credentials</h3>
                    
                    <div class="bg-gray-50 p-4 rounded mb-4">
                        <div class="text-sm font-medium text-gray-700 mb-2">API Key:</div>
                        <div class="font-mono text-sm bg-white p-3 rounded border border-gray-300 break-all">
                            {{ $company->api_key }}
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Use this in X-API-Key header or Authorization: Bearer header</p>
                    </div>
                    
                    <div class="bg-blue-50 p-4 rounded">
                        <div class="text-sm font-medium text-blue-900 mb-2">Base URL:</div>
                        <code class="text-sm text-blue-800">{{ url('/api/v1') }}</code>
                    </div>
                </div>
            </div>

            <!-- Create Verification -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">1. Create License Verification</h3>
                    
                    <div class="mb-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium bg-green-100 text-green-800">POST</span>
                        <code class="ml-2 text-sm">/api/v1/license-verifications</code>
                    </div>

                    <h4 class="font-semibold text-gray-800 mb-2">Request Headers:</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4"><code>X-API-Key: {{ $company->api_key }}
Content-Type: application/json</code></pre>

                    <h4 class="font-semibold text-gray-800 mb-2">Request Body:</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4"><code>{
  "code": "abn_alabama_gov:nursing",
  "license_type": "RN",
  "license_state": "CA",
  "license_number": "123456",
  "full_name": "Jane Doe",
  "npi": "1234567890",
  "city": "Los Angeles",
  "zip": "90001",
  "state": "CA",
  "category": "Registered Nurse"
}</code></pre>

                    <h4 class="font-semibold text-gray-800 mb-2">Required Fields:</h4>
                    <ul class="list-disc list-inside text-sm text-gray-700 mb-4 ml-4">
                        <li><code class="bg-gray-100 px-1 rounded">code</code> - CertOrg code (e.g., "abn_alabama_gov:nursing")</li>
                        <li><code class="bg-gray-100 px-1 rounded">license_type</code> - License type (e.g., "RN", "MD", "LPN")</li>
                        <li><code class="bg-gray-100 px-1 rounded">license_state</code> - 2-letter state code (e.g., "CA", "NY")</li>
                        <li><code class="bg-gray-100 px-1 rounded">license_number</code> - License number</li>
                        <li><code class="bg-gray-100 px-1 rounded">full_name</code> OR <code class="bg-gray-100 px-1 rounded">first_name</code> + <code class="bg-gray-100 px-1 rounded">last_name</code></li>
                    </ul>

                    <h4 class="font-semibold text-gray-800 mb-2">Optional Fields:</h4>
                    <ul class="list-disc list-inside text-sm text-gray-700 mb-4 ml-4">
                        <li><code class="bg-gray-100 px-1 rounded">npi</code> or <code class="bg-gray-100 px-1 rounded">npi_number</code> - NPI number</li>
                        <li><code class="bg-gray-100 px-1 rounded">city</code> - City</li>
                        <li><code class="bg-gray-100 px-1 rounded">zip</code> - ZIP code</li>
                        <li><code class="bg-gray-100 px-1 rounded">state</code> - State name or code</li>
                        <li><code class="bg-gray-100 px-1 rounded">category</code> - License category</li>
                    </ul>

                    <h4 class="font-semibold text-gray-800 mb-2">Response (201 Created):</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4"><code>{
  "request_id": "1",
  "deduped": false
}</code></pre>

                    <h4 class="font-semibold text-gray-800 mb-2">Response (200 OK - Duplicate):</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto"><code>{
  "request_id": "1",
  "deduped": true
}</code></pre>

                    <div class="mt-4 p-4 bg-blue-50 rounded">
                        <p class="text-sm text-blue-900">
                            <strong>Note:</strong> The verification processes asynchronously. Results will be sent to your webhook URL when complete.
                        </p>
                    </div>

                    <!-- Interactive CURL Test Section -->
                    <div class="mt-6 border-t pt-6">
                        <h4 class="font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                            Test API Request
                        </h4>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Request Payload (JSON):</label>
                            <textarea 
                                id="testPayload" 
                                rows="12" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                                spellcheck="false"
                           >{
  "code": "abn_alabama_gov:nursing",
  "license_type": "RN",
  "license_state": "CA",
  "license_number": "123456",
  "full_name": "Jane Doe",
  "npi": "1234567890",
  "city": "Los Angeles",
  "zip": "90001",
  "state": "CA"
}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Edit the JSON payload above and click "Send Request" to test the API</p>
                        </div>

                        <div class="flex items-center gap-3 mb-4">
                            <button 
                                id="sendRequestBtn"
                                onclick="sendTestRequest()"
                                class="inline-flex items-center px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-md transition duration-150 ease-in-out disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg id="loadingSpinner" class="hidden w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span id="btnText">Send Request</span>
                            </button>
                            <button 
                                onclick="resetPayload()"
                                class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium rounded-lg transition duration-150 ease-in-out"
                            >
                                Reset
                            </button>
                        </div>

                        <div id="responseSection" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Response:</label>
                            <div class="mb-2">
                                <span id="responseStatus" class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium"></span>
                                <span id="responseTime" class="ml-2 text-xs text-gray-500"></span>
                            </div>
                            <pre id="responseBody" class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-sm max-h-96 overflow-y-auto"></pre>
                            <button 
                                onclick="copyResponse()"
                                class="mt-2 inline-flex items-center px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm rounded transition duration-150 ease-in-out"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                Copy Response
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                const apiKey = '{{ $company->api_key }}';
                const apiUrl = '{{ url('/api/v1/license-verifications') }}';

                async function sendTestRequest() {
                    const btn = document.getElementById('sendRequestBtn');
                    const btnText = document.getElementById('btnText');
                    const spinner = document.getElementById('loadingSpinner');
                    const responseSection = document.getElementById('responseSection');
                    const responseStatus = document.getElementById('responseStatus');
                    const responseTime = document.getElementById('responseTime');
                    const responseBody = document.getElementById('responseBody');
                    const payloadTextarea = document.getElementById('testPayload');

                    // Disable button and show loading
                    btn.disabled = true;
                    btnText.textContent = 'Sending...';
                    spinner.classList.remove('hidden');
                    responseSection.classList.add('hidden');

                    try {
                        // Parse JSON payload
                        let payload;
                        try {
                            payload = JSON.parse(payloadTextarea.value);
                        } catch (e) {
                            throw new Error('Invalid JSON: ' + e.message);
                        }

                        // Record start time
                        const startTime = performance.now();

                        // Make API request
                        const response = await fetch(apiUrl, {
                            method: 'POST',
                            headers: {
                                'X-API-Key': apiKey,
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(payload)
                        });

                        // Calculate response time
                        const endTime = performance.now();
                        const responseTimeMs = Math.round(endTime - startTime);

                        // Get response data
                        let responseData;
                        const contentType = response.headers.get('content-type');
                        if (contentType && contentType.includes('application/json')) {
                            responseData = await response.json();
                        } else {
                            responseData = await response.text();
                        }

                        // Show response
                        responseSection.classList.remove('hidden');
                        
                        // Set status badge
                        if (response.ok) {
                            responseStatus.className = 'inline-flex items-center px-3 py-1 rounded-md text-sm font-medium bg-green-100 text-green-800';
                            responseStatus.textContent = `✓ ${response.status} ${response.statusText}`;
                        } else {
                            responseStatus.className = 'inline-flex items-center px-3 py-1 rounded-md text-sm font-medium bg-red-100 text-red-800';
                            responseStatus.textContent = `✗ ${response.status} ${response.statusText}`;
                        }

                        responseTime.textContent = `(${responseTimeMs}ms)`;
                        responseBody.textContent = JSON.stringify(responseData, null, 2);

                    } catch (error) {
                        // Show error
                        responseSection.classList.remove('hidden');
                        responseStatus.className = 'inline-flex items-center px-3 py-1 rounded-md text-sm font-medium bg-red-100 text-red-800';
                        responseStatus.textContent = '✗ Error';
                        responseTime.textContent = '';
                        responseBody.textContent = error.message;
                    } finally {
                        // Re-enable button
                        btn.disabled = false;
                        btnText.textContent = 'Send Request';
                        spinner.classList.add('hidden');
                    }
                }

                function resetPayload() {
                    document.getElementById('testPayload').value = `{
  "license_type": "RN",
  "license_state": "CA",
  "license_number": "123456",
  "full_name": "Jane Doe",
  "npi": "1234567890",
  "city": "Los Angeles",
  "zip": "90001",
  "state": "CA",
  "category": "Registered Nurse"
}`;
                    document.getElementById('responseSection').classList.add('hidden');
                }

                function copyResponse() {
                    const responseBody = document.getElementById('responseBody');
                    navigator.clipboard.writeText(responseBody.textContent).then(() => {
                        // Show brief feedback
                        const btn = event.target.closest('button');
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Copied!';
                        setTimeout(() => {
                            btn.innerHTML = originalText;
                        }, 2000);
                    });
                }

                // Allow Enter+Ctrl/Cmd to send request
                document.getElementById('testPayload').addEventListener('keydown', function(e) {
                    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                        e.preventDefault();
                        sendTestRequest();
                    }
                });
            </script>

            <!-- Update Verification -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">2. Update License Verification</h3>
                    
                    <div class="mb-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium bg-yellow-100 text-yellow-800">PUT</span>
                        <code class="ml-2 text-sm">/api/v1/license-verifications/{id}</code>
                    </div>

                    <h4 class="font-semibold text-gray-800 mb-2">Request Body (update any of these fields):</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4"><code>{
  "code": "abn_alabama_gov:nursing",
  "license_type": "RN",
  "license_state": "CA",
  "license_number": "123456",
  "full_name": "Jane Doe",
  "first_name": "Jane",
  "last_name": "Doe",
  "npi": "9876543210",
  "city": "San Francisco",
  "zip": "94105",
  "state": "CA",
  "category": "Registered Nurse"
}</code></pre>

                    <h4 class="font-semibold text-gray-800 mb-2">Updatable Fields:</h4>
                    <ul class="list-disc list-inside text-sm text-gray-700 mb-4 ml-4">
                        <li><code class="bg-gray-100 px-1 rounded">code</code> – Provider / profession key (same values as create; must exist in <code class="bg-gray-100 px-1 rounded">config/cert_org_codes</code>). Omit to keep the current code. Trimmed on save.</li>
                        <li><code class="bg-gray-100 px-1 rounded">license_type</code> – License type (e.g., RN, MD)</li>
                        <li><code class="bg-gray-100 px-1 rounded">license_state</code> – 2-letter state code (uppercased on save)</li>
                        <li><code class="bg-gray-100 px-1 rounded">license_number</code> – License number</li>
                        <li><code class="bg-gray-100 px-1 rounded">full_name</code> – Full name (optional if using first+last)</li>
                        <li><code class="bg-gray-100 px-1 rounded">first_name</code> / <code class="bg-gray-100 px-1 rounded">last_name</code> – Name parts</li>
                        <li><code class="bg-gray-100 px-1 rounded">npi</code> or <code class="bg-gray-100 px-1 rounded">npi_number</code> – NPI value</li>
                        <li><code class="bg-gray-100 px-1 rounded">city</code> – City</li>
                        <li><code class="bg-gray-100 px-1 rounded">zip</code> – ZIP / postal code</li>
                        <li><code class="bg-gray-100 px-1 rounded">state</code> – Mailing state / region (optional, separate from license_state)</li>
                        <li><code class="bg-gray-100 px-1 rounded">category</code> – License category / specialization</li>
                    </ul>

                    <h4 class="font-semibold text-gray-800 mb-2">Response (200 OK):</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4"><code>{
  "request_id": "1",
  "updated": true
}</code></pre>

                    <p class="text-sm text-gray-600 mb-2">
                        If the cert org for the effective <code class="bg-gray-100 px-1 rounded text-xs">code</code> (updated value or existing) is marked <strong>DOWN</strong>, the API returns <strong>503</strong> with the same payload shape as create — the row is not updated. A periodic probe may still be queued on this verification.
                    </p>
                </div>
            </div>

            <!-- Archive Verification -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">3. Archive License Verification</h3>
                    
                    <div class="mb-4">
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-sm font-medium bg-red-100 text-red-800">POST</span>
                        <code class="ml-2 text-sm">/api/v1/license-verifications/{id}/archive</code>
                    </div>

                    <h4 class="font-semibold text-gray-800 mb-2">Response (200 OK):</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto"><code>{
  "request_id": "1",
  "archived": true
}</code></pre>

                    <div class="mt-4 p-4 bg-yellow-50 rounded">
                        <p class="text-sm text-yellow-900">
                            <strong>Note:</strong> Archiving stops all future verification checks and NPPES retries.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Webhook Response Format -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Webhook Response (Sent to Your Endpoint)</h3>
                    
                    <p class="text-sm text-gray-700 mb-4">
                        When verification completes, we POST results to your configured webhook URL in <strong>MedEdge format</strong>:
                    </p>

                    <h4 class="font-semibold text-gray-800 mb-2">Webhook Payload - Success (Status "1"):</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4 text-xs"><code>{
  "success": true,
  "Request_ID": "1",
  "Response_msg": "Jane Doe Not Expired - [Registered Nurse] expiring [12/31/2026] via California: Board of Nursing (License Number of [123456])",
  "Status": "1",
  "Last_activity": "1/30/2026 5:45:32 PM",
  "Issued_to": "Jane Doe",
  "Expiration_date": "12/31/2026",
  "Certification_name": "Registered Nurse",
  "Provider": "California: Board of Nursing",
  "Status_Notes": "This item succeeded, but is awaiting post-success handling",
  "Client_Request_ID": "",
  "Screenshot": "data:image/jpg;base64,..."
}</code></pre>

                    <h4 class="font-semibold text-gray-800 mb-2">Webhook Payload - Expired (Status "2"):</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4 text-xs"><code>{
  "success": true,
  "Request_ID": "1",
  "Response_msg": "John Smith Lapsed - [Medical Doctor] expiring [6/30/2025] via Texas: Medical Board (License Number of [987654])",
  "Status": "2",
  "Last_activity": "1/30/2026 6:15:00 PM",
  "Issued_to": "John Smith",
  "Expiration_date": "06/30/2025",
  "Certification_name": "Medical Doctor",
  "Provider": "Texas: Medical Board",
  "Status_Notes": "This item succeeded, but is awaiting post-success handling",
  "Client_Request_ID": "",
  "Screenshot": "data:image/jpg;base64,..."
}</code></pre>

                    <h4 class="font-semibold text-gray-800 mb-2">Webhook Payload - Failed (Status "16"):</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4 text-xs"><code>{
  "success": false,
  "Request_ID": "1",
  "Response_msg": "Sarah Johnson/New York: Medical Board - Certification Not Found. The provider has no record of a License Number of [999999]. Verify the information and try again.",
  "Status": "16",
  "Last_activity": "1/30/2026 7:00:00 PM",
  "Issued_to": "",
  "Expiration_date": null,
  "Certification_name": "",
  "Provider": "New York: Medical Board",
  "Status_Notes": "This item encountered an error which needs to be handled",
  "Client_Request_ID": "",
  "Screenshot": "data:image/jpg;base64,"
}</code></pre>

                    <div class="mt-4 p-4 bg-green-50 rounded">
                        <h4 class="font-semibold text-green-900 mb-2">Status Codes:</h4>
                        <ul class="list-disc list-inside text-sm text-green-900 ml-4 space-y-1">
                            <li><strong>"1"</strong> = Verified and not expired</li>
                            <li><strong>"2"</strong> = Verified but expired</li>
                            <li><strong>"16"</strong> = Verification failed</li>
                        </ul>
                    </div>

                    <div class="mt-4 p-4 bg-purple-50 rounded">
                        <h4 class="font-semibold text-purple-900 mb-2">Webhook Headers:</h4>
                        <pre class="bg-white p-3 rounded border border-purple-200 text-xs mt-2"><code>Authorization: Bearer {auth0_access_token}
Content-Type: application/json
User-Agent: Valyd-Webhook/1.0</code></pre>
                        <p class="text-xs text-purple-900 mt-2">
                            Configure Auth0 credentials in <a href="{{ route('webhook-settings.edit') }}" class="underline">Webhook Settings</a>
                        </p>
                    </div>
                </div>
            </div>

            <!-- cURL Examples -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">cURL Examples</h3>
                    
                    <h4 class="font-semibold text-gray-800 mb-2">Create Verification:</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4 text-xs"><code>curl -X POST {{ url('/api/v1/license-verifications') }} \
  -H "X-API-Key: {{ $company->api_key }}" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "abn_alabama_gov:nursing",
    "license_type": "RN",
    "license_state": "CA",
    "license_number": "123456",
    "full_name": "Jane Doe",
    "npi": "1234567890"
  }'</code></pre>

                    <h4 class="font-semibold text-gray-800 mb-2">Update Verification:</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4 text-xs"><code>curl -X PUT {{ url('/api/v1/license-verifications/1') }} \
  -H "X-API-Key: {{ $company->api_key }}" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "search_dca_ca_gov:medicine",
    "npi": "9876543210"
  }'</code></pre>

                    <h4 class="font-semibold text-gray-800 mb-2">Archive Verification:</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs"><code>curl -X POST {{ url('/api/v1/license-verifications/1/archive') }} \
  -H "X-API-Key: {{ $company->api_key }}"</code></pre>
                </div>
            </div>

            <!-- Error Responses -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Error Responses</h3>
                    
                    <h4 class="font-semibold text-gray-800 mb-2">401 Unauthorized:</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4 text-xs"><code>{
  "error": "Unauthorized",
  "message": "API key is required. Provide X-API-Key header or Authorization: Bearer token."
}</code></pre>

                    <h4 class="font-semibold text-gray-800 mb-2">404 Not Found:</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto mb-4 text-xs"><code>{
  "error": "Not Found",
  "message": "License verification not found or does not belong to your company."
}</code></pre>

                    <h4 class="font-semibold text-gray-800 mb-2">422 Validation Error:</h4>
                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs"><code>{
  "error": "Validation Error",
  "message": "The given data was invalid.",
  "errors": {
    "license_state": ["The license state field is required."],
    "license_number": ["The license number field is required."]
  }
}</code></pre>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
