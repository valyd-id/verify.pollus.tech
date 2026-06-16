<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Webhook') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="rounded-md bg-green-50 p-4 border border-green-200">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            @endif
            @if(session('error'))
                <div class="rounded-md bg-red-50 p-4 border border-red-200">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            @endif
            
            <!-- Test URL -->
            <div class="bg-white overflow-visible shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Test Webhook URL</h3>
                    
                    <div class="bg-green-50 border-2 border-green-200 p-4 rounded-lg mb-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-green-900 mb-2">Use this URL in Webhook Settings:</p>
                                <code class="block bg-white px-4 py-3 rounded border border-green-300 text-sm break-all font-mono">{{ $webhookTestUrl }}</code>
                            </div>
                            <button onclick="copyToClipboard('{{ $webhookTestUrl }}')" class="ml-4 px-3 py-2 bg-green-600 text-white text-xs rounded hover:bg-green-700 flex-shrink-0">
                                Copy URL
                            </button>
                        </div>
                    </div>

                    <div class="bg-blue-50 p-4 rounded">
                        <h4 class="font-semibold text-blue-900 mb-2">How to Test:</h4>
                        <ol class="list-decimal list-inside text-sm text-blue-900 space-y-2">
                            <li>Copy the test URL above</li>
                            <li>Go to <a href="{{ route('webhook-settings.edit') }}" class="underline">Webhook Settings</a></li>
                            <li>Paste the URL in "Webhook URL" field</li>
                            <li>Leave Auth0 fields empty (not needed for testing)</li>
                            <li>Save settings</li>
                            <li>Create a test verification via API</li>
                            <li>Come back here to see the webhook data received</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="bg-white overflow-visible shadow-sm sm:rounded-lg relative z-30">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistics</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <p class="text-sm text-blue-600 font-medium">Total Logs</p>
                            <p class="text-2xl font-bold text-blue-900">{{ $stats['total'] }}</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <p class="text-sm text-green-600 font-medium">Successful</p>
                            <p class="text-2xl font-bold text-green-900">{{ $stats['successful'] }}</p>
                        </div>
                        <div class="bg-red-50 p-4 rounded-lg">
                            <p class="text-sm text-red-600 font-medium">Failed</p>
                            <p class="text-2xl font-bold text-red-900">{{ $stats['failed'] }}</p>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <p class="text-sm text-purple-600 font-medium">Today</p>
                            <p class="text-2xl font-bold text-purple-900">{{ $stats['today'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webhook Type Selector -->
            <div class="bg-white overflow-visible shadow-sm sm:rounded-lg relative z-30">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Webhook Type</h3>
                            <p class="text-sm text-gray-600">Switch between production, test, and provider down webhooks</p>
                        </div>
                        <div class="flex items-center space-x-2">
                            @php $currentType = $webhookType ?? 'production'; @endphp
                            <a href="{{ route('webhook-test.view', array_merge(request()->except('page'), ['type' => 'production'])) }}"
                               class="px-4 py-2 text-sm font-medium rounded-md {{ $currentType === 'production' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                Production
                            </a>
                            <a href="{{ route('webhook-test.view', array_merge(request()->except('page'), ['type' => 'test'])) }}"
                               class="px-4 py-2 text-sm font-medium rounded-md {{ $currentType === 'test' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                Test
                            </a>
                            <a href="{{ route('webhook-test.view', array_merge(request()->except('page'), ['type' => 'provider_down'])) }}"
                               class="px-4 py-2 text-sm font-medium rounded-md {{ $currentType === 'provider_down' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                Provider Down
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters & Actions -->
            <div class="bg-white overflow-visible shadow-sm sm:rounded-lg relative z-30">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Received Webhooks ({{ ucfirst($webhookType ?? 'production') }})</h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Showing {{ $logs->firstItem() ?? 0 }} to {{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} webhook(s)
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button onclick="window.location.reload()" class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Refresh
                            </button>
                            <!-- <button
                                type="button"
                                id="open-export-modal"
                                class="px-4 py-2 bg-emerald-600 text-white text-sm rounded hover:bg-emerald-700"
                                title="{{ __('Configure and download a CSV (Excel-compatible) of your verifications') }}"
                            >
                                {{ __('Export to Excel') }}
                            </button> -->
                            <a
                                href="{{ route('webhook-test.export', array_merge(request()->except('page'), ['format' => 'csv'])) }}"
                                class="inline-flex items-center px-4 py-2 bg-slate-700 text-white text-sm rounded hover:bg-slate-800"
                                title="{{ __('UTF-8 CSV with the same columns as the HTML export; honors filters below. Open in Excel.') }}"
                            >
                                {{ __('Webhook log CSV') }}
                            </a>
                            <a
                                href="{{ route('webhook-test.export', array_merge(request()->except('page'), ['format' => 'html'])) }}"
                                class="inline-flex items-center px-4 py-2 bg-slate-600 text-white text-sm rounded hover:bg-slate-700"
                                title="{{ __('HTML table download; honors filters below. Open in browser or Excel.') }}"
                            >
                                {{ __('Webhook log HTML') }}
                            </a>
                            @if($logs->total() > 0)
                                <form method="POST" action="{{ route('webhook-test.clear') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $webhookType ?? 'production' }}">
                                    <button type="submit" onclick="return confirm('Clear all {{ $webhookType ?? 'production' }} webhook logs?')" class="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                                        Clear All
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <!-- Filters -->
                    <form method="GET" action="{{ route('webhook-test.view') }}" class="flex flex-wrap items-end gap-2">
                        <input type="hidden" name="type" value="{{ $webhookType ?? 'production' }}">
                        <select name="status" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <option value="">All Status</option>
                            <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Verified (1)</option>
                            <option value="2" {{ request('status') == '2' ? 'selected' : '' }}>Expired (2)</option>
                            <option value="16" {{ request('status') == '16' ? 'selected' : '' }}>Failed (16)</option>
                        </select>
                        <select name="success" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                            <option value="">All Results</option>
                            <option value="1" {{ request('success') == '1' ? 'selected' : '' }}>Successful</option>
                            <option value="0" {{ request('success') == '0' ? 'selected' : '' }}>Failed</option>
                        </select>
                        <div class="flex flex-col">
                            <label for="verification_id" class="text-xs text-gray-500 mb-0.5">{{ __('Verification ID') }}</label>
                            <input
                                type="number"
                                id="verification_id"
                                name="verification_id"
                                min="1"
                                step="1"
                                value="{{ request('verification_id') }}"
                                placeholder="{{ __('e.g. 9070') }}"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm w-36"
                            >
                        </div>
                        <div class="flex flex-col">
                            <label for="npi" class="text-xs text-gray-500 mb-0.5">{{ __('NPI') }}</label>
                            <input
                                type="text"
                                id="npi"
                                name="npi"
                                value="{{ request('npi') }}"
                                placeholder="{{ __('e.g. 1234567890') }}"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm w-44"
                                inputmode="numeric"
                                autocomplete="off"
                            >
                        </div>
                        <div class="flex flex-col relative z-40" id="license-state-filter-wrap">
                            <label class="text-xs text-gray-500 mb-0.5">{{ __('License state') }}</label>
                            <button
                                type="button"
                                id="license-state-filter-toggle"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm min-w-[10rem] text-left bg-white hover:bg-gray-50 flex items-center justify-between"
                                aria-haspopup="true"
                                aria-expanded="false"
                            >
                                <span id="license-state-filter-label">{{ __('All') }}</span>
                                <span class="text-gray-400">▾</span>
                            </button>

                            <div
                                id="license-state-filter-panel"
                                class="hidden absolute z-50 mt-1 top-full left-0 w-64 bg-white border border-gray-200 rounded-md shadow-lg p-2"
                            >
                                <div class="max-h-48 overflow-auto space-y-1 pr-1">
                                    @foreach($webhookStates as $st)
                                        <label class="flex items-center gap-2 text-sm text-gray-800 px-1 py-0.5 rounded hover:bg-gray-50 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                value="{{ $st }}"
                                                class="license-state-filter-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                @checked(in_array($st, $licenseStates ?? [], true))
                                            >
                                            <span>{{ $st }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between">
                                    <button type="button" id="license-state-filter-clear" class="text-xs text-gray-600 hover:text-gray-900">{{ __('Clear') }}</button>
                                    <button type="button" id="license-state-filter-done" class="text-xs px-2 py-1 rounded bg-indigo-600 text-white hover:bg-indigo-700">{{ __('Done') }}</button>
                                </div>
                            </div>
                            <div id="license-state-filter-hidden-inputs">
                                @foreach(($licenseStates ?? []) as $selectedState)
                                    <input type="hidden" name="license_state[]" value="{{ $selectedState }}">
                                @endforeach
                            </div>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700">
                            Filter
                        </button>
                        @php
                            $webhookFiltersActive = filled(request('status'))
                                || (request()->has('success') && request('success') !== null && request('success') !== '')
                                || filled(request('verification_id'))
                                || filled(request('npi'))
                                || !empty($licenseStates);
                        @endphp
                        @if($webhookFiltersActive)
                            <a href="{{ route('webhook-test.view', ['type' => $webhookType ?? 'production']) }}" class="px-4 py-2 bg-gray-300 text-gray-700 text-sm rounded hover:bg-gray-400">
                                Clear Filters
                            </a>
                        @endif
                    </form>
                </div>
            </div>

            <!-- Export Records Modal (moved from dashboard) -->
            <dialog id="export-records-modal" class="max-w-lg w-[calc(100%-2rem)] rounded-lg border border-gray-200 p-0 shadow-2xl backdrop:bg-black/40">
                <form method="GET" action="{{ route('dashboard.export') }}" class="flex flex-col" id="export-records-form">
                    <div class="p-5 space-y-4">
                        <h4 class="text-lg font-semibold text-gray-900">{{ __('Export to Excel') }}</h4>
                        <p class="text-sm text-gray-600">
                            {{ __('Choose which verifications to include. A CSV file (opens directly in Excel) will be downloaded for your company only.') }}
                        </p>

                        <div>
                            <label for="export_filter" class="block text-sm font-medium text-gray-700 mb-2">{{ __('Status') }}</label>
                            <select
                                id="export_filter"
                                name="filter"
                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                                @foreach($exportFilters as $key => $label)
                                    <option value="{{ $key }}" @selected($key === 'recent')>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ __('“Recent” excludes archived. “All” includes every status except soft-deleted. “Archived” exports only archived records.') }}
                            </p>
                        </div>

                        @if($exportStates->isEmpty())
                            <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                                {{ __('No license states recorded yet. The export will include all states.') }}
                            </p>
                        @else
                            <fieldset>
                                <legend class="block text-sm font-medium text-gray-700 mb-2">{{ __('License state') }}</legend>
                                <div class="space-y-2">
                                    <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                        <input type="radio" name="export_state_scope" value="all" class="export-state-scope border-gray-300 text-emerald-600 focus:ring-emerald-500" checked>
                                        {{ __('All states') }}
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                        <input type="radio" name="export_state_scope" value="specific" class="export-state-scope border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                        {{ __('Specific state') }}
                                    </label>
                                </div>
                            </fieldset>

                            <div id="export-state-field" class="hidden">
                                <label for="export_license_state" class="block text-sm font-medium text-gray-700 mb-1">{{ __('State') }}</label>
                                <select
                                    id="export_license_state"
                                    name="license_state"
                                    class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                                    disabled
                                >
                                    <option value="">{{ __('Select state') }}</option>
                                    @foreach($exportStates as $st)
                                        <option value="{{ $st }}">{{ $st }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="flex items-start gap-2">
                            <input id="export_nppes_only" type="checkbox" name="nppes_only" value="1" class="mt-0.5 border-gray-300 rounded text-emerald-600 focus:ring-emerald-500">
                            <label for="export_nppes_only" class="text-sm text-gray-800">
                                {{ __('Only NPPES-verified records') }}
                                <span class="block text-xs text-gray-500">
                                    {{ __('Limits to records marked verified via NPPES / NPI lookup (status must be Verified).') }}
                                </span>
                            </label>
                        </div>

                        <div>
                            <label for="export_search" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Search (optional)') }}</label>
                            <input
                                id="export_search"
                                type="text"
                                name="search"
                                value=""
                                placeholder="{{ __('First/Last name, License no, NPI, Verification ID') }}"
                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            >
                        </div>

                        <div class="rounded-md bg-emerald-50 border border-emerald-200 px-3 py-2 text-xs text-emerald-900">
                            {{ __('File format: CSV (UTF-8 with BOM) — double-click to open in Excel. Columns: NAME, Npi, Success, ResponseMsg, Status, IssuedTo, Provider, ResponseDate, ExpirationDate, LicenseNumber, LicenseType, LicenseTypeFromPrescriber, LicenseState.') }}
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4 rounded-b-lg">
                        <button type="button" class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md" data-close-export-modal>
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                            {{ __('Download CSV') }}
                        </button>
                    </div>
                </form>
            </dialog>

            <script>
                (function () {
                    var modal = document.getElementById('export-records-modal');
                    var openBtn = document.getElementById('open-export-modal');
                    var form = document.getElementById('export-records-form');
                    var stateFieldWrap = document.getElementById('export-state-field');
                    var stateSelect = document.getElementById('export_license_state');
                    var nppesBox = document.getElementById('export_nppes_only');
                    var searchInput = document.getElementById('export_search');
                    if (!modal || !openBtn || !form) return;

                    function syncStateScopeUi() {
                        var specific = modal.querySelector('.export-state-scope[value="specific"]');
                        var isSpecific = specific && specific.checked;
                        if (stateFieldWrap) stateFieldWrap.classList.toggle('hidden', !isSpecific);
                        if (stateSelect) {
                            stateSelect.disabled = !isSpecific;
                            if (!isSpecific) stateSelect.value = '';
                        }
                    }

                    openBtn.addEventListener('click', function () {
                        if (modal.showModal) modal.showModal();
                        else modal.setAttribute('open', 'open');
                    });

                    modal.querySelectorAll('[data-close-export-modal]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            if (modal.close) modal.close();
                            else modal.removeAttribute('open');
                        });
                    });

                    modal.querySelectorAll('.export-state-scope').forEach(function (r) {
                        r.addEventListener('change', syncStateScopeUi);
                    });

                    form.addEventListener('submit', function () {
                        modal.querySelectorAll('.export-state-scope').forEach(function (r) {
                            r.disabled = true;
                        });
                        var specific = modal.querySelector('.export-state-scope[value="specific"]');
                        if (!(specific && specific.checked) && stateSelect) {
                            stateSelect.disabled = true;
                        }
                        if (nppesBox && !nppesBox.checked) nppesBox.disabled = true;
                        if (searchInput && !searchInput.value) searchInput.disabled = true;

                        setTimeout(function () {
                            if (modal.close) modal.close();
                        }, 250);
                    });

                    syncStateScopeUi();
                })();
            </script>

            <!-- Webhook Logs -->
            @if($logs->total() === 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No webhooks received yet</h3>
                            <p class="mt-1 text-sm text-gray-500">Configure the test URL in webhook settings and create a verification to see data here.</p>
                        </div>
                    </div>
                </div>
            @else
                @foreach($logs as $log)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-900">
                                        Webhook #{{ $log->id }}
                                        @if($log->success)
                                            <span class="ml-2 px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Success</span>
                                        @else
                                            <span class="ml-2 px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Failed</span>
                                        @endif
                                        @if($log->has_screenshot)
                                            <span class="ml-2 px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">📷 Screenshot</span>
                                        @endif
                                    </h4>
                                    <p class="text-sm text-gray-600">
                                        {{ $log->created_at->format('Y-m-d H:i:s') }} ({{ $log->created_at->diffForHumans() }})
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($log->request_id)
                                        <form method="POST" action="{{ route('webhook-test.resend', $log) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $webhookType ?? 'production' }}">
                                            <button type="submit" class="px-3 py-1.5 rounded-md text-xs font-medium bg-indigo-600 text-white hover:bg-indigo-700">
                                                Resend Webhook
                                            </button>
                                        </form>
                                    @endif
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                        {{ $log->method }}
                                    </span>
                                    @if($log->webhook_type === 'production')
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-800">
                                            Production
                                        </span>
                                    @else
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                                            Test
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Quick Info -->
                            <div class="mb-4 bg-gray-50 p-4 rounded-lg">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                    @if($log->request_id)
                                        <div>
                                            <span class="text-gray-600">Request ID:</span>
                                            <span class="font-semibold text-gray-900 ml-1">{{ $log->request_id }}</span>
                                        </div>
                                    @endif
                                    @if($log->status)
                                        <div>
                                            <span class="text-gray-600">Status:</span>
                                            <span class="font-semibold ml-1 
                                                {{ $log->status == '1' ? 'text-green-600' : '' }}
                                                {{ $log->status == '2' ? 'text-yellow-600' : '' }}
                                                {{ $log->status == '16' ? 'text-red-600' : '' }}
                                            ">
                                                {{ $log->status }}
                                                @if($log->status == '1')
                                                    (Verified)
                                                @elseif($log->status == '2')
                                                    (Expired)
                                                @elseif($log->status == '16')
                                                    (Failed)
                                                @endif
                                            </span>
                                        </div>
                                    @endif
                                    @if($log->issued_to)
                                        <div>
                                            <span class="text-gray-600">Issued To:</span>
                                            <span class="font-semibold text-gray-900 ml-1">{{ $log->issued_to }}</span>
                                        </div>
                                    @endif
                                    @if($log->expiration_date)
                                        <div>
                                            <span class="text-gray-600">Expires:</span>
                                            <span class="font-semibold text-gray-900 ml-1">{{ $log->expiration_date }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Request Details -->
                            <div class="mb-4">
                                <h5 class="font-semibold text-gray-800 mb-2">Request URL:</h5>
                                <code class="block bg-gray-100 px-3 py-2 rounded text-xs break-all">{{ $log->url }}</code>
                            </div>

                            <!-- Headers -->
                            <div class="mb-4">
                                <details class="group">
                                    <summary class="cursor-pointer font-semibold text-gray-800 mb-2 hover:text-indigo-600">
                                        Headers (click to expand)
                                    </summary>
                                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs mt-2">{{ json_encode($log->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            </div>

                            <!-- Payload -->
                            <div class="mb-4">
                                <details class="group">
                                    <summary class="cursor-pointer font-semibold text-gray-800 mb-2 hover:text-indigo-600">
                                        Full Payload (click to expand)
                                    </summary>
                                    <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs mt-2">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            </div>

                            <!-- Production Webhook Response (only for production webhooks) -->
                            @if($log->webhook_type === 'production' && ($log->response_status !== null || $log->response_body !== null))
                                <div class="mb-4 border-t pt-4">
                                    <h5 class="font-semibold text-gray-800 mb-2">Production Webhook Response:</h5>
                                    @if($log->response_status !== null)
                                        <div class="mb-2">
                                            <span class="text-gray-600">Response Status:</span>
                                            <span class="ml-2 px-2 py-1 rounded text-xs font-semibold 
                                                {{ $log->response_status >= 200 && $log->response_status < 300 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $log->response_status }}
                                            </span>
                                        </div>
                                    @endif
                                    @if($log->response_body !== null)
                                        <details class="group">
                                            <summary class="cursor-pointer font-semibold text-gray-800 mb-2 hover:text-indigo-600">
                                                Response Body (click to expand)
                                            </summary>
                                            <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs mt-2 max-h-96 overflow-y-auto">{{ $log->response_body }}</pre>
                                        </details>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                <!-- Pagination -->
                @if($logs->hasPages())
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            {{ $logs->links() }}
                        </div>
                    </div>
                @endif
            @endif

        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('URL copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }

        (function () {
            var wrap = document.getElementById('license-state-filter-wrap');
            if (!wrap) return;

            var toggle = document.getElementById('license-state-filter-toggle');
            var panel = document.getElementById('license-state-filter-panel');
            var label = document.getElementById('license-state-filter-label');
            var hiddenInputs = document.getElementById('license-state-filter-hidden-inputs');
            var clearBtn = document.getElementById('license-state-filter-clear');
            var doneBtn = document.getElementById('license-state-filter-done');
            var checkboxes = Array.prototype.slice.call(
                wrap.querySelectorAll('.license-state-filter-checkbox')
            );

            function selectedValues() {
                return checkboxes.filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
            }

            function syncHiddenInputs() {
                hiddenInputs.innerHTML = '';
                selectedValues().forEach(function (value) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'license_state[]';
                    input.value = value;
                    hiddenInputs.appendChild(input);
                });
            }

            function syncLabel() {
                var selected = selectedValues();
                if (selected.length === 0) {
                    label.textContent = 'All';
                } else if (selected.length === 1) {
                    label.textContent = selected[0];
                } else {
                    label.textContent = selected.length + ' states selected';
                }
            }

            function openPanel() {
                panel.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
            }

            function closePanel() {
                panel.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
            }

            toggle.addEventListener('click', function () {
                if (panel.classList.contains('hidden')) openPanel();
                else closePanel();
            });

            doneBtn.addEventListener('click', function () {
                closePanel();
            });

            clearBtn.addEventListener('click', function () {
                checkboxes.forEach(function (cb) { cb.checked = false; });
                syncHiddenInputs();
                syncLabel();
            });

            checkboxes.forEach(function (cb) {
                cb.addEventListener('change', function () {
                    syncHiddenInputs();
                    syncLabel();
                });
            });

            document.addEventListener('click', function (event) {
                if (!wrap.contains(event.target)) closePanel();
            });

            syncHiddenInputs();
            syncLabel();
        })();

        // Webhook type selection handled by anchor links.
    </script>
</x-app-layout>
