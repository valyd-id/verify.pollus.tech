<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if ($openIncidents->isNotEmpty() || $downProviderAlertsWithoutIncident->isNotEmpty())
                <div class="mb-6 space-y-3">
                    @foreach ($openIncidents as $incident)
                        @php
                            $contributors = is_array($incident->contributors) ? $incident->contributors : [];
                            $contributorsCount = count($contributors);
                            $detailsId = 'incident-details-'.$incident->id;
                            $incidentTitle = $incident->state_name ?: $incident->code;
                            $showIncidentCodeChip = filled($incident->state_name) && (string) $incident->state_name !== (string) $incident->code;
                        @endphp
                        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-600 text-white text-xs font-semibold uppercase tracking-wide">
                                            {{ __('Provider DOWN') }}
                                        </span>
                                        <span class="font-medium">{{ $incidentTitle }}</span>
                                        @if ($showIncidentCodeChip)
                                            <span class="text-xs text-red-700 font-mono">{{ $incident->code }}</span>
                                        @endif
                                        @if ($incident->source === 'manual')
                                            <span class="text-xs text-red-700 italic">({{ __('manual') }})</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-red-800">
                                        {{ __('Started') }}: {{ optional($incident->started_at)->diffForHumans() ?? '—' }}
                                        @if ($incident->failure_count > 0)
                                            · {{ __(':f failures in last :n attempts', ['f' => $incident->failure_count, 'n' => $incident->attempts_in_window]) }}
                                        @endif
                                        @if (! empty($incident->last_error_code))
                                            · {{ __('Code') }}: {{ $incident->last_error_code }}
                                        @endif
                                    </div>
                                    @if (! empty($incident->last_error))
                                        <div class="text-xs text-red-800 max-w-3xl truncate" title="{{ $incident->last_error }}">
                                            {{ $incident->last_error }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    @if ($contributorsCount > 0)
                                        <button
                                            type="button"
                                            class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-medium rounded-md text-red-800 bg-white hover:bg-red-100"
                                            onclick="document.getElementById('{{ $detailsId }}').classList.toggle('hidden')"
                                        >
                                            {{ __('View :n contributing license(s)', ['n' => $contributorsCount]) }}
                                        </button>
                                    @else
                                        <span class="text-xs text-red-700 italic">{{ __('No window snapshot available') }}</span>
                                    @endif
                                </div>
                            </div>

                            @if ($contributorsCount > 0)
                                <div id="{{ $detailsId }}" class="hidden mt-3 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-red-200 text-xs">
                                        <thead class="bg-red-100/60 text-red-900 uppercase tracking-wide">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-medium">{{ __('ID') }}</th>
                                                <th class="px-3 py-2 text-left font-medium">{{ __('License') }}</th>
                                                <th class="px-3 py-2 text-left font-medium">{{ __('Name') }}</th>
                                                <th class="px-3 py-2 text-left font-medium">{{ __('NPI') }}</th>
                                                <th class="px-3 py-2 text-left font-medium">{{ __('Error') }}</th>
                                                <th class="px-3 py-2 text-left font-medium">{{ __('When') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-red-100 bg-white text-red-900">
                                            @foreach ($contributors as $c)
                                                <tr>
                                                    <td class="px-3 py-2 font-medium">
                                                        @if (! empty($c['verification_id']))
                                                            <a class="text-indigo-700 hover:underline"
                                                               href="{{ route('dashboard.verifications.show', $c['verification_id']) }}">
                                                                #{{ $c['verification_id'] }}
                                                            </a>
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        {{ trim(($c['license_type'] ?? '').' '.($c['license_state'] ?? '').' '.($c['license_number'] ?? '')) ?: '—' }}
                                                    </td>
                                                    <td class="px-3 py-2">{{ $c['name'] ?? '—' }}</td>
                                                    <td class="px-3 py-2">{{ $c['npi'] ?? '—' }}</td>
                                                    <td class="px-3 py-2">
                                                        @if (! empty($c['error_code']))
                                                            <span class="font-mono">{{ $c['error_code'] }}</span>
                                                        @endif
                                                        @if (! empty($c['error_message']))
                                                            <div class="text-[11px] text-red-700 max-w-xs truncate" title="{{ $c['error_message'] }}">
                                                                {{ $c['error_message'] }}
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        @if (! empty($c['recorded_at']))
                                                            {{ \Carbon\Carbon::parse($c['recorded_at'])->diffForHumans() }}
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @foreach ($downProviderAlertsWithoutIncident as $alert)
                        @php
                            /** @var \App\Models\CertOrgStatus $cos */
                            $cos = $alert['cert_org'];
                            $waitingCount = (int) ($alert['waiting_count'] ?? 0);
                            $cosTitle = $cos->state_name ?: $cos->code;
                            $showCosCodeChip = filled($cos->state_name) && (string) $cos->state_name !== (string) $cos->code;
                        @endphp
                        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                <div class="space-y-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-600 text-white text-xs font-semibold uppercase tracking-wide">
                                            {{ __('Provider DOWN') }}
                                        </span>
                                        <span class="font-medium">{{ $cosTitle }}</span>
                                        @if ($showCosCodeChip)
                                            <span class="text-xs text-red-700 font-mono">{{ $cos->code }}</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-red-800">
                                        {{ __('Last checked') }}: {{ optional($cos->last_checked_at)->diffForHumans() ?? '—' }}
                                        @if (! empty($cos->last_error_code))
                                            · {{ __('Code') }}: {{ $cos->last_error_code }}
                                        @endif
                                        @if ($waitingCount > 0)
                                            · {{ $waitingCount }} {{ $waitingCount === 1 ? __('verification') : __('verifications') }} {{ __('waiting for this provider') }}
                                        @endif
                                    </div>
                                    @if (! empty($cos->last_error))
                                        <div class="text-xs text-red-800 max-w-3xl truncate" title="{{ $cos->last_error }}">
                                            {{ $cos->last_error }}
                                        </div>
                                    @endif
                                    <div class="text-xs text-red-700 italic">
                                        {{ __('No open incident snapshot for this code; status is DOWN in provider health.') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($nppesOnlySuppressed)
                <div
                    class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    role="status"
                >
                    {{ __('"NPPES verified" only applies when the filter is Recent, All, Verified, or Archived. It was ignored for your current status filter so counts and the list stay meaningful.') }}
                </div>
            @endif

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase">Total Verifications</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">{{ $stats['total_verifications'] }}</div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase">Active</div>
                        <div class="mt-2 text-3xl font-bold text-blue-600">{{ $stats['active_verifications'] }}</div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase">Verified</div>
                        <div class="mt-2 text-3xl font-bold text-green-600">{{ $stats['verified'] }}</div>
                    </div>
                </div>
                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase">Pending</div>
                        <div class="mt-2 text-3xl font-bold text-yellow-600">{{ $stats['pending'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Additional Status Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase">{{ __('Waiting for provider') }}</div>
                        <div class="mt-2 text-3xl font-bold text-amber-700">{{ $stats['waiting_provider'] }}</div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase">Not Found</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900">{{ $stats['not_found'] }}</div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase">Mismatch</div>
                        <div class="mt-2 text-3xl font-bold text-orange-600">{{ $stats['mismatch'] }}</div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase">Error</div>
                        <div class="mt-2 text-3xl font-bold text-red-600">{{ $stats['error'] }}</div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="text-sm font-medium text-gray-500 uppercase">Archived</div>
                        <div class="mt-2 text-3xl font-bold text-gray-500">{{ $stats['archived'] }}</div>
                    </div>
                </div>
            </div>

            <!-- API Key Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">API Configuration</h3>
                    <div class="bg-gray-50 p-4 rounded">
                        <div class="text-sm font-medium text-gray-700 mb-2">Your API Key:</div>
                        <div class="font-mono text-sm bg-white p-3 rounded border border-gray-300 break-all">
                            {{ $company->api_key }}
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Use this API key in your X-API-Key header or Authorization: Bearer header.</p>
                    </div>
                    
                    <div class="mt-4 bg-blue-50 p-4 rounded">
                        <h4 class="text-sm font-semibold text-blue-900 mb-2">API Endpoint:</h4>
                        <code class="text-xs text-blue-800">POST {{ url('/api/v1/license-verifications') }}</code>
                    </div>
                </div>
            </div>

            <!-- Verifications List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-3">
                        <div class="flex flex-col gap-2">
                            <h3 class="text-lg font-semibold text-gray-900">Verifications</h3>
                            <!-- <p class="text-xs text-gray-500 max-w-3xl">
                                {{ __('Summary cards use state, search, created-at range, and NPPES (when compatible with your filter). Total and Active are totals for that scope—not limited by the status dropdown. The table below follows all filters including status.') }}
                            </p> -->
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    id="open-reverify-records-modal"
                                    class="inline-flex items-center px-3 py-1.5 border border-indigo-300 text-xs font-medium rounded-md text-indigo-800 bg-white hover:bg-indigo-50"
                                >
                                    {{ __('Reverify Records') }}
                                </button>
                                @if(($pendingNotFoundHoldCount ?? 0) > 0)
                                    <button
                                        type="button"
                                        id="open-send-held-not-found-modal"
                                        class="inline-flex items-center px-3 py-1.5 border border-sky-400 text-xs font-medium rounded-md text-sky-900 bg-white hover:bg-sky-50"
                                        title="{{ __(':count held not_found production webhook(s)', ['count' => $pendingNotFoundHoldCount]) }}"
                                    >
                                        {{ __('Send held not_found (:count)', ['count' => $pendingNotFoundHoldCount]) }}
                                    </button>
                                @endif
                                @if(($errorWebhookSendEligibleCount ?? 0) > 0)
                                    <button
                                        type="button"
                                        id="open-send-error-webhooks-modal"
                                        class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-medium rounded-md text-red-900 bg-white hover:bg-red-50"
                                        title="{{ __(':count error row(s) can send a not_found or mismatch webhook', ['count' => $errorWebhookSendEligibleCount]) }}"
                                    >
                                        {{ __('Send error webhooks (:count)', ['count' => $errorWebhookSendEligibleCount]) }}
                                    </button>
                                @endif
                                @if(($manualReviewWebhookEligibleCount ?? 0) > 0)
                                    <button
                                        type="button"
                                        id="open-send-manual-review-webhooks-modal"
                                        class="inline-flex items-center px-3 py-1.5 border border-purple-400 text-xs font-medium rounded-md text-purple-900 bg-white hover:bg-purple-50"
                                        title="{{ __(':count manual review production webhook(s)', ['count' => $manualReviewWebhookEligibleCount]) }}"
                                    >
                                        {{ __('Send manual review webhooks (:count)', ['count' => $manualReviewWebhookEligibleCount]) }}
                                    </button>
                                @endif
                            </div>

                            <dialog id="reverify-records-modal" class="max-w-lg w-[calc(100%-2rem)] rounded-lg border border-gray-200 p-0 shadow-2xl backdrop:bg-black/40">
                                <form method="POST" action="{{ route('dashboard.verifications.reverify-bulk') }}" class="flex flex-col">
                                    @csrf
                                    <div class="p-5 space-y-4">
                                        <h4 class="text-lg font-semibold text-gray-900">{{ __('Reverify records') }}</h4>
                                        <p class="text-sm text-gray-600">
                                            {{ __('Rows are taken in stable ID order (next in queue). Records already verifying are skipped. Only the rows you queue are set to pending; the rest stay in their current status and can be queued later.') }}
                                        </p>

                                        <script type="application/json" id="reverify-eligible-data">@json($reverifyEligibleCounts)</script>

                                        <div>
                                            <label for="reverify_status" class="block text-sm font-medium text-gray-700 mb-2">{{ __('Record type') }}</label>
                                            <select
                                                id="reverify_status"
                                                name="status"
                                                required
                                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="all" @selected(old('status') === 'all')>{{ __('All (pending, verifying, not found, mismatch, error, verified)') }}</option>
                                                <option value="pending" @selected(old('status') === 'pending')>{{ __('Pending / verifying') }}</option>
                                                <option value="error" @selected(old('status', 'error') === 'error')>{{ __('Error') }}</option>
                                                <option value="mismatch" @selected(old('status') === 'mismatch')>{{ __('Mismatch') }}</option>
                                                <option value="not_found" @selected(old('status') === 'not_found')>{{ __('Not found') }}</option>
                                                <option value="verified" @selected(old('status') === 'verified')>{{ __('Verified') }}</option>
                                            </select>
                                        </div>

                                        <p class="text-sm text-gray-700">
                                            {{ __('Eligible for this queue') }}:
                                            <strong id="reverify-eligible-total" class="text-gray-900 tabular-nums">0</strong>
                                        </p>

                                        @if($states->isEmpty())
                                            <input type="hidden" name="license_state_scope" value="all">
                                            <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                                                {{ __('No license states found in your verifications yet. Reverification applies to all states.') }}
                                            </p>
                                        @else
                                            <fieldset>
                                                <legend class="block text-sm font-medium text-gray-700 mb-2">{{ __('License state') }}</legend>
                                                <div class="space-y-2">
                                                    <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                                        <input
                                                            type="radio"
                                                            name="license_state_scope"
                                                            value="all"
                                                            class="reverify-state-scope border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                            @checked(old('license_state_scope', 'all') === 'all')
                                                        >
                                                        {{ __('All states') }}
                                                    </label>
                                                    <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                                        <input
                                                            type="radio"
                                                            name="license_state_scope"
                                                            value="specific"
                                                            class="reverify-state-scope border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                            @checked(old('license_state_scope') === 'specific')
                                                        >
                                                        {{ __('Specific state') }}
                                                    </label>
                                                </div>
                                            </fieldset>

                                            <div id="reverify-state-field" class="@if(old('license_state_scope') !== 'specific' && !$errors->has('license_state')) hidden @endif">
                                                <label for="reverify_license_state" class="block text-sm font-medium text-gray-700 mb-1">{{ __('State') }}</label>
                                                <select
                                                    id="reverify_license_state"
                                                    name="license_state"
                                                    class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                >
                                                    <option value="">{{ __('Select state') }}</option>
                                                    @foreach($states as $st)
                                                        <option value="{{ $st }}" @selected(old('license_state') === $st)>{{ $st }}</option>
                                                    @endforeach
                                                </select>
                                                @error('license_state')
                                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @endif

                                        <fieldset>
                                            <legend class="block text-sm font-medium text-gray-700 mb-2">{{ __('How many') }}</legend>
                                            <div class="space-y-2">
                                                <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="scope"
                                                        value="all"
                                                        class="reverify-scope border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                        @checked(old('scope', 'all') === 'all')
                                                    >
                                                    {{ __('All eligible records (for the selected type)') }}
                                                </label>
                                                <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        name="scope"
                                                        value="limited"
                                                        class="reverify-scope border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                        @checked(old('scope') === 'limited')
                                                    >
                                                    {{ __('Only the next N records in the queue') }}
                                                </label>
                                            </div>
                                        </fieldset>

                                        <div id="reverify-count-field" class="@if(old('scope') !== 'limited' && !$errors->has('count')) hidden @endif">
                                            <label for="reverify_count" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Number of records') }}</label>
                                            <input
                                                type="number"
                                                id="reverify_count"
                                                name="count"
                                                min="1"
                                                max="10000"
                                                value="{{ old('count') }}"
                                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="{{ __('e.g. 50') }}"
                                            >
                                            @error('count')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        @if ($errors->hasAny(['status', 'scope', 'license_state_scope', 'license_state']))
                                            <div class="rounded-md bg-red-50 p-3 text-sm text-red-800 space-y-1">
                                                @foreach (['status', 'scope', 'license_state_scope', 'license_state'] as $reverifyField)
                                                    @foreach ($errors->get($reverifyField, []) as $msg)
                                                        <p>{{ $msg }}</p>
                                                    @endforeach
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4 rounded-b-lg">
                                        <button
                                            type="button"
                                            class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md"
                                            data-close-reverify-modal
                                        >
                                            {{ __('Cancel') }}
                                        </button>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            {{ __('Queue reverification') }}
                                        </button>
                                    </div>
                                </form>
                            </dialog>

                            @if(($pendingNotFoundHoldCount ?? 0) > 0)
                                <dialog id="send-held-not-found-modal" class="max-w-lg w-[calc(100%-2rem)] rounded-lg border border-gray-200 p-0 shadow-2xl backdrop:bg-black/40">
                                    <form method="POST" action="{{ route('dashboard.webhooks.send-pending-not-found') }}" class="flex flex-col">
                                        @csrf
                                        <div class="p-5 space-y-4">
                                            <h4 class="text-lg font-semibold text-gray-900">{{ __('Send held not_found webhooks') }}</h4>
                                            <p class="text-sm text-gray-600">
                                                {{ __('Production webhooks currently on hold for not_found outcomes are sent immediately. Choose all license states or only one state.') }}
                                            </p>

                                            <script type="application/json" id="held-nf-counts-data">@json([
                                                'all' => $pendingNotFoundHoldCount,
                                                'byState' => $pendingNotFoundHoldByState ?? [],
                                            ])</script>

                                            <p class="text-sm text-gray-700">
                                                {{ __('Held in this scope') }}:
                                                <strong id="held-nf-scope-count" class="text-gray-900 tabular-nums">{{ $pendingNotFoundHoldCount }}</strong>
                                            </p>

                                            @if($states->isEmpty())
                                                <input type="hidden" name="held_nf_license_state_scope" value="all">
                                                <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                                                    {{ __('No license states found in your verifications yet. Sends apply to all held rows.') }}
                                                </p>
                                            @else
                                                <fieldset>
                                                    <legend class="block text-sm font-medium text-gray-700 mb-2">{{ __('License state') }}</legend>
                                                    <div class="space-y-2">
                                                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                                            <input
                                                                type="radio"
                                                                name="held_nf_license_state_scope"
                                                                value="all"
                                                                class="held-nf-state-scope border-gray-300 text-sky-600 focus:ring-sky-500"
                                                                @checked(old('held_nf_license_state_scope', 'all') === 'all')
                                                            >
                                                            {{ __('All states') }}
                                                        </label>
                                                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                                            <input
                                                                type="radio"
                                                                name="held_nf_license_state_scope"
                                                                value="specific"
                                                                class="held-nf-state-scope border-gray-300 text-sky-600 focus:ring-sky-500"
                                                                @checked(old('held_nf_license_state_scope') === 'specific')
                                                            >
                                                            {{ __('Specific state') }}
                                                        </label>
                                                    </div>
                                                </fieldset>

                                                <div id="held-nf-state-field" class="@if(old('held_nf_license_state_scope') !== 'specific' && !$errors->has('held_nf_license_state')) hidden @endif">
                                                    <label for="held_nf_license_state" class="block text-sm font-medium text-gray-700 mb-1">{{ __('State') }}</label>
                                                    <select
                                                        id="held_nf_license_state"
                                                        name="held_nf_license_state"
                                                        class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-sky-500 focus:ring-sky-500"
                                                    >
                                                        <option value="">{{ __('Select state') }}</option>
                                                        @foreach($states as $st)
                                                            <option value="{{ $st }}" @selected(old('held_nf_license_state') === $st)>{{ $st }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('held_nf_license_state')
                                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            @endif

                                            @if ($errors->hasAny(['held_nf_license_state_scope', 'held_nf_license_state']))
                                                <div class="rounded-md bg-red-50 p-3 text-sm text-red-800 space-y-1">
                                                    @foreach (['held_nf_license_state_scope', 'held_nf_license_state'] as $nfField)
                                                        @foreach ($errors->get($nfField, []) as $msg)
                                                            <p>{{ $msg }}</p>
                                                        @endforeach
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4 rounded-b-lg">
                                            <button
                                                type="button"
                                                class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md"
                                                data-close-held-nf-modal
                                            >
                                                {{ __('Cancel') }}
                                            </button>
                                            <button
                                                type="submit"
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sky-500"
                                            >
                                                {{ __('Send webhooks') }}
                                            </button>
                                        </div>
                                    </form>
                                </dialog>

                                <script>
                                    (function () {
                                        var modal = document.getElementById('send-held-not-found-modal');
                                        var openBtn = document.getElementById('open-send-held-not-found-modal');
                                        var dataEl = document.getElementById('held-nf-counts-data');
                                        var countEl = document.getElementById('held-nf-scope-count');
                                        var stateFieldWrap = document.getElementById('held-nf-state-field');
                                        var stateSelect = document.getElementById('held_nf_license_state');
                                        var counts = { all: 0, byState: {} };
                                        if (dataEl) {
                                            try {
                                                counts = JSON.parse(dataEl.textContent) || counts;
                                            } catch (e) {
                                                counts = { all: 0, byState: {} };
                                            }
                                        }
                                        if (!modal || !openBtn) return;

                                        function syncHeldNfStateUi() {
                                            var specific = modal.querySelector('.held-nf-state-scope[value="specific"]');
                                            var isSpecific = specific && specific.checked;
                                            if (stateFieldWrap) stateFieldWrap.classList.toggle('hidden', !isSpecific);
                                            if (stateSelect) {
                                                stateSelect.disabled = !isSpecific;
                                                if (isSpecific) stateSelect.setAttribute('required', 'required');
                                                else stateSelect.removeAttribute('required');
                                            }
                                        }

                                        function updateHeldNfScopeCount() {
                                            if (!countEl) return;
                                            var specific = modal.querySelector('.held-nf-state-scope[value="specific"]');
                                            var isSpecific = specific && specific.checked;
                                            var byState = counts.byState || {};
                                            var n;
                                            if (isSpecific && stateSelect && stateSelect.value) {
                                                n = byState[stateSelect.value];
                                                if (typeof n === 'undefined' || n === null) n = 0;
                                            } else {
                                                n = counts.all;
                                                if (typeof n === 'undefined' || n === null) n = 0;
                                            }
                                            countEl.textContent = String(n);
                                        }

                                        openBtn.addEventListener('click', function () {
                                            if (modal.showModal) modal.showModal();
                                            syncHeldNfStateUi();
                                            updateHeldNfScopeCount();
                                        });
                                        modal.querySelectorAll('[data-close-held-nf-modal]').forEach(function (btn) {
                                            btn.addEventListener('click', function () {
                                                modal.close();
                                            });
                                        });
                                        modal.querySelectorAll('.held-nf-state-scope').forEach(function (r) {
                                            r.addEventListener('change', function () {
                                                syncHeldNfStateUi();
                                                updateHeldNfScopeCount();
                                            });
                                        });
                                        if (stateSelect) stateSelect.addEventListener('change', updateHeldNfScopeCount);

                                        syncHeldNfStateUi();
                                        updateHeldNfScopeCount();

                                        @if(old('held_nf_license_state_scope') !== null || $errors->hasAny(['held_nf_license_state_scope', 'held_nf_license_state']))
                                        if (modal.showModal) modal.showModal();
                                        @endif
                                    })();
                                </script>
                            @endif

                            @if(($manualReviewWebhookEligibleCount ?? 0) > 0)
                                <dialog id="send-manual-review-webhooks-modal" class="max-w-lg w-[calc(100%-2rem)] rounded-lg border border-gray-200 p-0 shadow-2xl backdrop:bg-black/40">
                                    <form method="POST" action="{{ route('dashboard.webhooks.send-pending') }}" class="flex flex-col">
                                        @csrf
                                        <div class="p-5 space-y-4">
                                            <h4 class="text-lg font-semibold text-gray-900">{{ __('Send manual review webhooks') }}</h4>
                                            <p class="text-sm text-gray-600">
                                                {{ __('Choose outcome and scope. Verified uses provider status per row (active → Status 1, expired/lapsed → Status 2). Rows with screenshot issues are skipped and stay on hold.') }}
                                            </p>

                                            <script type="application/json" id="manual-review-webhook-counts-data">@json([
                                                'all' => $manualReviewWebhookEligibleCount,
                                                'byState' => $manualReviewWebhookEligibleByState ?? [],
                                            ])</script>

                                            <p class="text-sm text-gray-700">
                                                {{ __('Pending in this scope') }}:
                                                <strong id="manual-review-scope-count" class="text-gray-900 tabular-nums">{{ $manualReviewWebhookEligibleCount }}</strong>
                                            </p>

                                            <div>
                                                <label for="send-manual-review-bulk-outcome" class="block text-sm font-medium text-gray-700 mb-1">{{ __('License outcome') }}</label>
                                                <select
                                                    name="manual_outcome"
                                                    id="send-manual-review-bulk-outcome"
                                                    required
                                                    class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                                >
                                                    <option value="" disabled @selected(! old('manual_outcome'))>{{ __('Select…') }}</option>
                                                    <option value="verified_auto" @selected(old('manual_outcome') === 'verified_auto')>{{ __('Verified — auto active / expired per row') }}</option>
                                                    <option value="not_found" @selected(old('manual_outcome') === 'not_found')>{{ __('Not found') }}</option>
                                                    <option value="mismatch" @selected(old('manual_outcome') === 'mismatch')>{{ __('Mismatch') }}</option>
                                                </select>
                                                @error('manual_outcome')
                                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div id="send-manual-review-bulk-mismatch-wrap" class="@if(old('manual_outcome') !== 'mismatch' && !$errors->has('mismatch_dimension')) hidden @endif">
                                                <label for="send-manual-review-bulk-mismatch-dimension" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Mismatch details') }}</label>
                                                <select
                                                    name="mismatch_dimension"
                                                    id="send-manual-review-bulk-mismatch-dimension"
                                                    class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                                >
                                                    <option value="">{{ __('Select…') }}</option>
                                                    <option value="name" @selected(old('mismatch_dimension') === 'name')>{{ __('Name does not match board record') }}</option>
                                                    <option value="license_type" @selected(old('mismatch_dimension') === 'license_type')>{{ __('License type / profession does not match board record') }}</option>
                                                </select>
                                                @error('mismatch_dimension')
                                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div>
                                                <label for="send-manual-review-bulk-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Notes (Status_Notes)') }}</label>
                                                <textarea
                                                    name="manual_notes"
                                                    id="send-manual-review-bulk-notes"
                                                    rows="3"
                                                    maxlength="5000"
                                                    class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                                    placeholder="{{ __('Optional — appended to every row') }}"
                                                >{{ old('manual_notes') }}</textarea>
                                            </div>

                                            @if($states->isEmpty())
                                                <input type="hidden" name="manual_review_state_scope" value="all">
                                                <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">
                                                    {{ __('No license states found in your verifications yet. Sends apply to all pending manual review rows.') }}
                                                </p>
                                            @else
                                                <fieldset>
                                                    <legend class="block text-sm font-medium text-gray-700 mb-2">{{ __('License state') }}</legend>
                                                    <div class="space-y-2">
                                                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                                            <input
                                                                type="radio"
                                                                name="manual_review_state_scope"
                                                                value="all"
                                                                class="manual-review-state-scope border-gray-300 text-purple-600 focus:ring-purple-500"
                                                                @checked(old('manual_review_state_scope', 'all') === 'all')
                                                            >
                                                            {{ __('All states') }}
                                                        </label>
                                                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                                            <input
                                                                type="radio"
                                                                name="manual_review_state_scope"
                                                                value="specific"
                                                                class="manual-review-state-scope border-gray-300 text-purple-600 focus:ring-purple-500"
                                                                @checked(old('manual_review_state_scope') === 'specific')
                                                            >
                                                            {{ __('Specific state') }}
                                                        </label>
                                                    </div>
                                                </fieldset>

                                                <div id="manual-review-state-field" class="@if(old('manual_review_state_scope') !== 'specific' && !$errors->has('manual_review_license_state')) hidden @endif">
                                                    <label for="manual_review_license_state" class="block text-sm font-medium text-gray-700 mb-1">{{ __('State') }}</label>
                                                    <select
                                                        id="manual_review_license_state"
                                                        name="manual_review_license_state"
                                                        class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-purple-500 focus:ring-purple-500"
                                                    >
                                                        <option value="">{{ __('Select state') }}</option>
                                                        @foreach($states as $st)
                                                            <option value="{{ $st }}" @selected(old('manual_review_license_state') === $st)>{{ $st }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('manual_review_license_state')
                                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            @endif

                                            @if ($errors->hasAny(['manual_review_state_scope', 'manual_review_license_state', 'manual_outcome', 'mismatch_dimension']))
                                                <div class="rounded-md bg-red-50 p-3 text-sm text-red-800 space-y-1">
                                                    @foreach (['manual_review_state_scope', 'manual_review_license_state', 'manual_outcome', 'mismatch_dimension'] as $mrField)
                                                        @foreach ($errors->get($mrField, []) as $msg)
                                                            <p>{{ $msg }}</p>
                                                        @endforeach
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4 rounded-b-lg">
                                            <button
                                                type="button"
                                                class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md"
                                                data-close-manual-review-bulk-modal
                                            >
                                                {{ __('Cancel') }}
                                            </button>
                                            <button
                                                type="submit"
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
                                            >
                                                {{ __('Send webhooks') }}
                                            </button>
                                        </div>
                                    </form>
                                </dialog>

                                <script>
                                    (function () {
                                        var modal = document.getElementById('send-manual-review-webhooks-modal');
                                        var openBtn = document.getElementById('open-send-manual-review-webhooks-modal');
                                        var dataEl = document.getElementById('manual-review-webhook-counts-data');
                                        var countEl = document.getElementById('manual-review-scope-count');
                                        var stateFieldWrap = document.getElementById('manual-review-state-field');
                                        var stateSelect = document.getElementById('manual_review_license_state');
                                        var outcomeSel = document.getElementById('send-manual-review-bulk-outcome');
                                        var mismatchWrap = document.getElementById('send-manual-review-bulk-mismatch-wrap');
                                        var mismatchSel = document.getElementById('send-manual-review-bulk-mismatch-dimension');
                                        var counts = { all: 0, byState: {} };
                                        if (dataEl) {
                                            try {
                                                counts = JSON.parse(dataEl.textContent) || counts;
                                            } catch (e) {
                                                counts = { all: 0, byState: {} };
                                            }
                                        }
                                        if (!modal || !openBtn) return;

                                        function syncManualReviewMismatchUi() {
                                            if (!outcomeSel || !mismatchWrap || !mismatchSel) return;
                                            var isMismatch = outcomeSel.value === 'mismatch';
                                            mismatchWrap.classList.toggle('hidden', !isMismatch);
                                            if (isMismatch) {
                                                mismatchSel.setAttribute('required', 'required');
                                            } else {
                                                mismatchSel.removeAttribute('required');
                                                mismatchSel.value = '';
                                            }
                                        }

                                        function syncManualReviewStateUi() {
                                            var specific = modal.querySelector('.manual-review-state-scope[value="specific"]');
                                            var isSpecific = specific && specific.checked;
                                            if (stateFieldWrap) stateFieldWrap.classList.toggle('hidden', !isSpecific);
                                            if (stateSelect) {
                                                stateSelect.disabled = !isSpecific;
                                                if (isSpecific) stateSelect.setAttribute('required', 'required');
                                                else stateSelect.removeAttribute('required');
                                            }
                                        }

                                        function updateManualReviewScopeCount() {
                                            if (!countEl) return;
                                            var specific = modal.querySelector('.manual-review-state-scope[value="specific"]');
                                            var isSpecific = specific && specific.checked;
                                            var byState = counts.byState || {};
                                            var n;
                                            if (isSpecific && stateSelect && stateSelect.value) {
                                                n = byState[stateSelect.value];
                                                if (typeof n === 'undefined' || n === null) n = 0;
                                            } else {
                                                n = counts.all;
                                                if (typeof n === 'undefined' || n === null) n = 0;
                                            }
                                            countEl.textContent = String(n);
                                        }

                                        openBtn.addEventListener('click', function () {
                                            if (modal.showModal) modal.showModal();
                                            syncManualReviewMismatchUi();
                                            syncManualReviewStateUi();
                                            updateManualReviewScopeCount();
                                        });
                                        modal.querySelectorAll('[data-close-manual-review-bulk-modal]').forEach(function (btn) {
                                            btn.addEventListener('click', function () {
                                                modal.close();
                                            });
                                        });
                                        modal.querySelectorAll('.manual-review-state-scope').forEach(function (r) {
                                            r.addEventListener('change', function () {
                                                syncManualReviewStateUi();
                                                updateManualReviewScopeCount();
                                            });
                                        });
                                        if (stateSelect) stateSelect.addEventListener('change', updateManualReviewScopeCount);
                                        if (outcomeSel) outcomeSel.addEventListener('change', syncManualReviewMismatchUi);

                                        syncManualReviewMismatchUi();
                                        syncManualReviewStateUi();
                                        updateManualReviewScopeCount();

                                        @if(old('manual_review_state_scope') !== null || old('manual_outcome') !== null || $errors->hasAny(['manual_review_state_scope', 'manual_review_license_state', 'manual_outcome', 'mismatch_dimension']))
                                        if (modal.showModal) modal.showModal();
                                        @endif
                                    })();
                                </script>
                            @endif

                            <dialog id="send-webhook-modal" class="max-w-md w-[calc(100%-2rem)] rounded-lg border border-gray-200 p-0 shadow-2xl backdrop:bg-black/40">
                                <form id="send-webhook-form" method="POST" action="" class="flex flex-col">
                                    @csrf
                                    <div class="p-5 space-y-4">
                                        <h4 class="text-lg font-semibold text-gray-900">{{ __('Send production webhook') }}</h4>
                                        <p class="text-sm text-gray-600">
                                            {{ __('Choose the license outcome for MedEdge. Optional notes are appended to Status_Notes.') }}
                                        </p>
                                        <div>
                                            <label for="send-webhook-manual-outcome" class="block text-sm font-medium text-gray-700 mb-1">{{ __('License outcome') }}</label>
                                            <select
                                                name="manual_outcome"
                                                id="send-webhook-manual-outcome"
                                                required
                                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="" disabled selected>{{ __('Select…') }}</option>
                                                <option value="verified_active">{{ __('Verified — active') }}</option>
                                                <option value="verified_expired">{{ __('Verified — expired / lapsed') }}</option>
                                                <option value="not_found">{{ __('Not found') }}</option>
                                                <option value="mismatch">{{ __('Mismatch') }}</option>
                                            </select>
                                        </div>
                                        <div id="send-webhook-mismatch-wrap" class="hidden">
                                            <label for="send-webhook-mismatch-dimension" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Mismatch details') }}</label>
                                            <select
                                                name="mismatch_dimension"
                                                id="send-webhook-mismatch-dimension"
                                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="">{{ __('Select…') }}</option>
                                                <option value="name">{{ __('Name does not match board record') }}</option>
                                                <option value="license_type">{{ __('License type / profession does not match board record') }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="send-webhook-manual-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Notes (Status_Notes)') }}</label>
                                            <textarea
                                                name="manual_notes"
                                                id="send-webhook-manual-notes"
                                                rows="3"
                                                maxlength="5000"
                                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="{{ __('Optional — shown to the client in Status_Notes') }}"
                                            ></textarea>
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4 rounded-b-lg">
                                        <button
                                            type="button"
                                            class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md"
                                            data-close-send-webhook-modal
                                        >
                                            {{ __('Cancel') }}
                                        </button>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500"
                                        >
                                            {{ __('Send webhook') }}
                                        </button>
                                    </div>
                                </form>
                            </dialog>

                            <script>
                                (function () {
                                    var swModal = document.getElementById('send-webhook-modal');
                                    var swForm = document.getElementById('send-webhook-form');
                                    var swOutcome = document.getElementById('send-webhook-manual-outcome');
                                    var swMismatchWrap = document.getElementById('send-webhook-mismatch-wrap');
                                    var swMismatchSel = document.getElementById('send-webhook-mismatch-dimension');
                                    var swNotes = document.getElementById('send-webhook-manual-notes');

                                    function syncMismatchUi() {
                                        if (! swOutcome || ! swMismatchWrap || ! swMismatchSel) return;
                                        var isMismatch = swOutcome.value === 'mismatch';
                                        swMismatchWrap.classList.toggle('hidden', ! isMismatch);
                                        if (isMismatch) {
                                            swMismatchSel.setAttribute('required', 'required');
                                        } else {
                                            swMismatchSel.removeAttribute('required');
                                            swMismatchSel.value = '';
                                        }
                                    }

                                    if (swOutcome) {
                                        swOutcome.addEventListener('change', syncMismatchUi);
                                    }

                                    var legacySendConfirmMsg = @json(__('Send production webhook using the automatic payload from verification results?'));

                                    // Event delegation: this script runs before the verification table exists in the DOM,
                                    // so querySelectorAll('.js-open-send-webhook-modal') would attach zero listeners.
                                    document.addEventListener('click', function (event) {
                                        var btn = event.target.closest('.js-open-send-webhook-modal');
                                        if (! btn) {
                                            return;
                                        }
                                        event.preventDefault();
                                        var url = btn.getAttribute('data-send-url');
                                        if (! url) return;
                                        var needsManual = btn.getAttribute('data-requires-manual-outcome') === '1';
                                        if (! needsManual) {
                                            if (! window.confirm(legacySendConfirmMsg)) {
                                                return;
                                            }
                                            var f = document.createElement('form');
                                            f.method = 'POST';
                                            f.action = url;
                                            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
                                            if (csrfMeta && csrfMeta.getAttribute('content')) {
                                                var tok = document.createElement('input');
                                                tok.type = 'hidden';
                                                tok.name = '_token';
                                                tok.value = csrfMeta.getAttribute('content');
                                                f.appendChild(tok);
                                            }
                                            document.body.appendChild(f);
                                            f.submit();
                                            return;
                                        }
                                        if (! swForm) return;
                                        swForm.setAttribute('action', url);
                                        if (swOutcome) swOutcome.selectedIndex = 0;
                                        if (swMismatchSel) swMismatchSel.value = '';
                                        if (swNotes) swNotes.value = '';
                                        syncMismatchUi();
                                        if (swModal && swModal.showModal) swModal.showModal();
                                    });

                                    if (swModal) {
                                        swModal.querySelectorAll('[data-close-send-webhook-modal]').forEach(function (b) {
                                            b.addEventListener('click', function () {
                                                swModal.close();
                                            });
                                        });
                                    }
                                })();
                            </script>

                            <dialog id="send-error-row-webhook-modal" class="max-w-md w-[calc(100%-2rem)] rounded-lg border border-gray-200 p-0 shadow-2xl backdrop:bg-black/40">
                                <form id="send-error-row-webhook-form" method="POST" action="" class="flex flex-col">
                                    @csrf
                                    <div class="p-5 space-y-4">
                                        <h4 class="text-lg font-semibold text-gray-900">{{ __('Send webhook (error row)') }}</h4>
                                        <p class="text-sm text-gray-600">
                                            {{ __('Choose not_found or mismatch. License status updates to match after a successful client response (same as manual review send).') }}
                                        </p>
                                        <div>
                                            <label for="send-error-row-manual-outcome" class="block text-sm font-medium text-gray-700 mb-1">{{ __('License outcome') }}</label>
                                            <select
                                                name="manual_outcome"
                                                id="send-error-row-manual-outcome"
                                                required
                                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="" disabled selected>{{ __('Select…') }}</option>
                                                <option value="not_found">{{ __('Not found') }}</option>
                                                <option value="mismatch">{{ __('Mismatch') }}</option>
                                            </select>
                                        </div>
                                        <div id="send-error-row-mismatch-wrap" class="hidden">
                                            <label for="send-error-row-mismatch-dimension" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Mismatch details') }}</label>
                                            <select
                                                name="mismatch_dimension"
                                                id="send-error-row-mismatch-dimension"
                                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="">{{ __('Select…') }}</option>
                                                <option value="name">{{ __('Name does not match board record') }}</option>
                                                <option value="license_type">{{ __('License type / profession does not match board record') }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="send-error-row-manual-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Notes (Status_Notes)') }}</label>
                                            <textarea
                                                name="manual_notes"
                                                id="send-error-row-manual-notes"
                                                rows="3"
                                                maxlength="5000"
                                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="{{ __('Optional — shown to the client in Status_Notes') }}"
                                            ></textarea>
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4 rounded-b-lg">
                                        <button
                                            type="button"
                                            class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md"
                                            data-close-send-error-row-webhook-modal
                                        >
                                            {{ __('Cancel') }}
                                        </button>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        >
                                            {{ __('Send webhook') }}
                                        </button>
                                    </div>
                                </form>
                            </dialog>

                            <dialog id="send-error-webhooks-bulk-modal" class="max-w-md w-[calc(100%-2rem)] rounded-lg border border-gray-200 p-0 shadow-2xl backdrop:bg-black/40">
                                <form id="send-error-webhooks-bulk-form" method="POST" action="{{ route('dashboard.webhooks.send-all-error') }}" class="flex flex-col">
                                    @csrf
                                    <input type="hidden" name="filter" value="{{ $activeFilter }}">
                                    <input type="hidden" name="license_state" value="{{ $activeLicenseState }}">
                                    <input type="hidden" name="license_type" value="{{ $activeLicenseType ?? '' }}">
                                    <input type="hidden" name="search" value="{{ $search }}">
                                    <input type="hidden" name="created_from" value="{{ $createdFromInput }}">
                                    <input type="hidden" name="created_to" value="{{ $createdToInput }}">
                                    @if ($manualReviewOnly ?? false)
                                        <input type="hidden" name="manual_review_only" value="1">
                                    @endif
                                    @if ($nppesOnlyActive)
                                        <input type="hidden" name="nppes_only" value="1">
                                    @endif
                                    <div class="p-5 space-y-4">
                                        <h4 class="text-lg font-semibold text-gray-900">{{ __('Send all eligible error webhooks') }}</h4>
                                        <p class="text-sm text-gray-600">
                                            {{ __('Applies to error rows in the current dashboard scope (:count eligible). First 300 IDs are processed. Same outcome for every row.', ['count' => $errorWebhookSendEligibleCount ?? 0]) }}
                                        </p>
                                        <div>
                                            <label for="send-error-bulk-manual-outcome" class="block text-sm font-medium text-gray-700 mb-1">{{ __('License outcome') }}</label>
                                            <select
                                                name="manual_outcome"
                                                id="send-error-bulk-manual-outcome"
                                                required
                                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="" disabled selected>{{ __('Select…') }}</option>
                                                <option value="not_found">{{ __('Not found') }}</option>
                                                <option value="mismatch">{{ __('Mismatch') }}</option>
                                            </select>
                                        </div>
                                        <div id="send-error-bulk-mismatch-wrap" class="hidden">
                                            <label for="send-error-bulk-mismatch-dimension" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Mismatch details') }}</label>
                                            <select
                                                name="mismatch_dimension"
                                                id="send-error-bulk-mismatch-dimension"
                                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            >
                                                <option value="">{{ __('Select…') }}</option>
                                                <option value="name">{{ __('Name does not match board record') }}</option>
                                                <option value="license_type">{{ __('License type / profession does not match board record') }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="send-error-bulk-manual-notes" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Notes (Status_Notes)') }}</label>
                                            <textarea
                                                name="manual_notes"
                                                id="send-error-bulk-manual-notes"
                                                rows="3"
                                                maxlength="5000"
                                                class="w-full text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="{{ __('Optional — appended to every row') }}"
                                            ></textarea>
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-4 rounded-b-lg">
                                        <button
                                            type="button"
                                            class="px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-md"
                                            data-close-send-error-bulk-modal
                                        >
                                            {{ __('Cancel') }}
                                        </button>
                                        <button
                                            type="submit"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        >
                                            {{ __('Send all') }}
                                        </button>
                                    </div>
                                </form>
                            </dialog>

                            <script>
                                (function () {
                                    var rowModal = document.getElementById('send-error-row-webhook-modal');
                                    var rowForm = document.getElementById('send-error-row-webhook-form');
                                    var rowOutcome = document.getElementById('send-error-row-manual-outcome');
                                    var rowMismatchWrap = document.getElementById('send-error-row-mismatch-wrap');
                                    var rowMismatchSel = document.getElementById('send-error-row-mismatch-dimension');
                                    var rowNotes = document.getElementById('send-error-row-manual-notes');

                                    function syncRowMismatchUi() {
                                        if (! rowOutcome || ! rowMismatchWrap || ! rowMismatchSel) return;
                                        var isMismatch = rowOutcome.value === 'mismatch';
                                        rowMismatchWrap.classList.toggle('hidden', ! isMismatch);
                                        if (isMismatch) {
                                            rowMismatchSel.setAttribute('required', 'required');
                                        } else {
                                            rowMismatchSel.removeAttribute('required');
                                            rowMismatchSel.value = '';
                                        }
                                    }

                                    if (rowOutcome) {
                                        rowOutcome.addEventListener('change', syncRowMismatchUi);
                                    }

                                    document.addEventListener('click', function (event) {
                                        var btn = event.target.closest('.js-open-error-row-webhook-modal');
                                        if (! btn) {
                                            return;
                                        }
                                        event.preventDefault();
                                        var url = btn.getAttribute('data-send-url');
                                        if (! url || ! rowForm) return;
                                        rowForm.setAttribute('action', url);
                                        if (rowOutcome) rowOutcome.selectedIndex = 0;
                                        if (rowMismatchSel) rowMismatchSel.value = '';
                                        if (rowNotes) rowNotes.value = '';
                                        syncRowMismatchUi();
                                        if (rowModal && rowModal.showModal) rowModal.showModal();
                                    });

                                    if (rowModal) {
                                        rowModal.querySelectorAll('[data-close-send-error-row-webhook-modal]').forEach(function (b) {
                                            b.addEventListener('click', function () {
                                                rowModal.close();
                                            });
                                        });
                                    }

                                    var bulkModal = document.getElementById('send-error-webhooks-bulk-modal');
                                    var openBulk = document.getElementById('open-send-error-webhooks-modal');
                                    var bulkOutcome = document.getElementById('send-error-bulk-manual-outcome');
                                    var bulkMismatchWrap = document.getElementById('send-error-bulk-mismatch-wrap');
                                    var bulkMismatchSel = document.getElementById('send-error-bulk-mismatch-dimension');
                                    var bulkNotes = document.getElementById('send-error-bulk-manual-notes');

                                    function syncBulkMismatchUi() {
                                        if (! bulkOutcome || ! bulkMismatchWrap || ! bulkMismatchSel) return;
                                        var isMismatch = bulkOutcome.value === 'mismatch';
                                        bulkMismatchWrap.classList.toggle('hidden', ! isMismatch);
                                        if (isMismatch) {
                                            bulkMismatchSel.setAttribute('required', 'required');
                                        } else {
                                            bulkMismatchSel.removeAttribute('required');
                                            bulkMismatchSel.value = '';
                                        }
                                    }

                                    if (bulkOutcome) {
                                        bulkOutcome.addEventListener('change', syncBulkMismatchUi);
                                    }

                                    if (openBulk && bulkModal) {
                                        openBulk.addEventListener('click', function () {
                                            if (bulkOutcome) bulkOutcome.selectedIndex = 0;
                                            if (bulkMismatchSel) bulkMismatchSel.value = '';
                                            if (bulkNotes) bulkNotes.value = '';
                                            syncBulkMismatchUi();
                                            if (bulkModal.showModal) bulkModal.showModal();
                                        });
                                        bulkModal.querySelectorAll('[data-close-send-error-bulk-modal]').forEach(function (b) {
                                            b.addEventListener('click', function () {
                                                bulkModal.close();
                                            });
                                        });
                                    }
                                })();
                            </script>

                            <script>
                                (function () {
                                    var modal = document.getElementById('reverify-records-modal');
                                    var openBtn = document.getElementById('open-reverify-records-modal');
                                    var countWrap = document.getElementById('reverify-count-field');
                                    var countInput = document.getElementById('reverify_count');
                                    var dataEl = document.getElementById('reverify-eligible-data');
                                    var statusSelect = document.getElementById('reverify_status');
                                    var totalEl = document.getElementById('reverify-eligible-total');
                                    var stateFieldWrap = document.getElementById('reverify-state-field');
                                    var stateSelect = document.getElementById('reverify_license_state');
                                    var eligibleCounts = {};
                                    if (dataEl) {
                                        try {
                                            eligibleCounts = JSON.parse(dataEl.textContent) || {};
                                        } catch (e) {
                                            eligibleCounts = {};
                                        }
                                    }
                                    if (!modal || !openBtn) return;

                                    function syncCountVisibility() {
                                        var limited = modal.querySelector('.reverify-scope[value="limited"]');
                                        var isLimited = limited && limited.checked;
                                        if (countWrap) countWrap.classList.toggle('hidden', !isLimited);
                                        if (countInput) {
                                            if (isLimited) countInput.setAttribute('required', 'required');
                                            else countInput.removeAttribute('required');
                                        }
                                    }

                                    function syncStateScopeUi() {
                                        var specific = modal.querySelector('.reverify-state-scope[value="specific"]');
                                        var isSpecific = specific && specific.checked;
                                        if (stateFieldWrap) stateFieldWrap.classList.toggle('hidden', !isSpecific);
                                        if (stateSelect) {
                                            stateSelect.disabled = !isSpecific;
                                            if (isSpecific) stateSelect.setAttribute('required', 'required');
                                            else stateSelect.removeAttribute('required');
                                        }
                                    }

                                    function updateEligibleTotal() {
                                        if (!totalEl || !statusSelect) return;
                                        var st = statusSelect.value;
                                        var bucket = eligibleCounts[st] || { all: 0, byState: {} };
                                        var specific = modal.querySelector('.reverify-state-scope[value="specific"]');
                                        var isSpecific = specific && specific.checked;
                                        var n;
                                        if (isSpecific && stateSelect && stateSelect.value) {
                                            n = bucket.byState[stateSelect.value];
                                            if (typeof n === 'undefined' || n === null) n = 0;
                                        } else {
                                            n = bucket.all;
                                        }
                                        totalEl.textContent = String(n);
                                    }

                                    openBtn.addEventListener('click', function () {
                                        if (modal.showModal) modal.showModal();
                                        updateEligibleTotal();
                                    });
                                    modal.querySelectorAll('[data-close-reverify-modal]').forEach(function (btn) {
                                        btn.addEventListener('click', function () {
                                            modal.close();
                                        });
                                    });
                                    modal.querySelectorAll('.reverify-scope').forEach(function (r) {
                                        r.addEventListener('change', syncCountVisibility);
                                    });
                                    modal.querySelectorAll('.reverify-state-scope').forEach(function (r) {
                                        r.addEventListener('change', function () {
                                            syncStateScopeUi();
                                            updateEligibleTotal();
                                        });
                                    });
                                    if (statusSelect) statusSelect.addEventListener('change', updateEligibleTotal);
                                    if (stateSelect) stateSelect.addEventListener('change', updateEligibleTotal);

                                    syncCountVisibility();
                                    syncStateScopeUi();
                                    updateEligibleTotal();

                                    @if(old('scope') === 'limited' || $errors->hasAny(['count', 'status', 'scope', 'license_state_scope', 'license_state']))
                                    if (modal.showModal) modal.showModal();
                                    @endif
                                })();
                            </script>
                        </div>
                        <form id="dashboard-filters-form" method="GET" action="{{ route('dashboard') }}" class="w-full rounded-md border border-gray-200 bg-gray-50/60 p-3">
                            <input type="hidden" name="per_page" value="{{ $perPage }}">
                            <div class="flex flex-wrap items-end gap-3">
                                <div class="min-w-[7rem]">
                                    <label for="license_state" class="block text-xs font-medium text-gray-600 mb-1">{{ __('State') }}</label>
                                    <select
                                        id="license_state"
                                        name="license_state"
                                        class="w-full text-sm border-gray-300 rounded-md shadow-sm"
                                    >
                                        <option value="" @selected(empty($activeLicenseState))>All</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state }}" @selected($activeLicenseState === $state)>{{ $state }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="min-w-[8rem]">
                                    <label for="license_type_filter" class="block text-xs font-medium text-gray-600 mb-1">{{ __('License type') }}</label>
                                    <select
                                        id="license_type_filter"
                                        name="license_type"
                                        class="w-full text-sm border-gray-300 rounded-md shadow-sm"
                                    >
                                        <option value="" @selected(empty($activeLicenseType ?? ''))>{{ __('All') }}</option>
                                        @foreach($licenseTypes ?? [] as $lt)
                                            <option value="{{ $lt }}" @selected(($activeLicenseType ?? '') === $lt)>{{ $lt }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="min-w-[12rem]">
                                    <label for="filter" class="block text-xs font-medium text-gray-600 mb-1">{{ __('Filter') }}</label>
                                    <select id="filter" name="filter" class="w-full text-sm border-gray-300 rounded-md shadow-sm">
                                        @foreach($filters as $key => $label)
                                            <option value="{{ $key }}" @selected($activeFilter === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="min-w-[22rem] flex-1">
                                    <label for="search" class="block text-xs font-medium text-gray-600 mb-1">{{ __('Search') }}</label>
                                    <input
                                        id="search"
                                        name="search"
                                        type="text"
                                        value="{{ $search }}"
                                        placeholder="First/Last name, License no, NPI, Verification ID"
                                        class="w-full text-sm border-gray-300 rounded-md shadow-sm"
                                    />
                                </div>

                                <div class="min-w-[12rem]">
                                    <label for="created_from" class="block text-xs font-medium text-gray-600 mb-1">{{ __('Created from') }}</label>
                                    <input
                                        id="created_from"
                                        name="created_from"
                                        type="datetime-local"
                                        value="{{ $createdFromInput }}"
                                        class="w-full text-sm border-gray-300 rounded-md shadow-sm"
                                    />
                                </div>

                                <div class="min-w-[12rem]">
                                    <label for="created_to" class="block text-xs font-medium text-gray-600 mb-1">{{ __('Created to') }}</label>
                                    <input
                                        id="created_to"
                                        name="created_to"
                                        type="datetime-local"
                                        value="{{ $createdToInput }}"
                                        class="w-full text-sm border-gray-300 rounded-md shadow-sm"
                                    />
                                </div>

                                <div class="pb-[1px]">
                                    <label class="relative inline-flex items-center cursor-pointer select-none"
                                           title="{{ __('Rows whose latest production webhook is pending manual outcome before send.') }}">
                                        <input
                                            type="checkbox"
                                            name="manual_review_only"
                                            value="1"
                                            class="sr-only peer"
                                            @checked($manualReviewOnly ?? false)
                                        />
                                        <span
                                            class="px-3 py-2 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white
                                                   hover:bg-gray-50
                                                   peer-checked:bg-violet-600 peer-checked:border-violet-600 peer-checked:text-white"
                                        >
                                            {{ __('Manual review') }}
                                        </span>
                                    </label>
                                </div>

                                <div class="pb-[1px]">
                                    <label class="relative inline-flex items-center cursor-pointer select-none"
                                           title="{{ __('Narrows to NPPES/NPI–verified rows. Only used with filter: Recent, All, Verified, or Archived.') }}">
                                        <input
                                            type="checkbox"
                                            name="nppes_only"
                                            value="1"
                                            class="sr-only peer"
                                            @checked($nppesOnlyActive)
                                        />
                                        <span
                                            class="px-3 py-2 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white
                                                   hover:bg-gray-50
                                                   peer-checked:bg-blue-600 peer-checked:border-blue-600 peer-checked:text-white"
                                        >
                                            {{ __('NPPES verified') }}
                                        </span>
                                    </label>
                                </div>

                                <div class="ml-auto flex items-center gap-2">
                                    <a
                                        href="{{ route('dashboard', ['per_page' => $perPage]) }}"
                                        class="inline-flex items-center px-3 py-2 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-100"
                                    >
                                        {{ __('Reset') }}
                                    </a>
                                    <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        {{ __('Apply') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                        <script>
                            (function () {
                                var form = document.getElementById('dashboard-filters-form');
                                if (!form) return;
                                var filterSelect = form.querySelector('#filter');
                                var nppesInput = form.querySelector('input[name="nppes_only"]');
                                if (!filterSelect || !nppesInput) return;
                                var nppesCompatibleFilters = { all: true, recent: true, verified: true, archived: true };
                                function clearNppesIfIncompatible() {
                                    if (!nppesCompatibleFilters[filterSelect.value] && nppesInput.checked) {
                                        nppesInput.checked = false;
                                    }
                                }
                                filterSelect.addEventListener('change', clearNppesIfIncompatible);
                            })();
                        </script>
                    </div>

                    @if($verifications->isEmpty())
                        <p class="text-gray-500">No verifications found for this filter.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">License</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NPI</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Try Info</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($verifications as $verification)
                                        @php
                                            $canReverify = ! $verification->trashed() && $verification->archived_at === null;
                                            $latestDelivery = $verification->latestWebhookDelivery;
                                            $hasPendingProductionWebhook = $latestDelivery
                                                && $latestDelivery->succeeded_at === null;
                                            $pendingWebhookNeedsManualOutcome = $hasPendingProductionWebhook
                                                && $latestDelivery->manual_check_required;
                                            $pendingNotFoundHold = $hasPendingProductionWebhook
                                                && $latestDelivery->pending_not_found_hold;
                                            $pendingScreenshotIssue = $hasPendingProductionWebhook
                                                && ! $pendingWebhookNeedsManualOutcome
                                                && ! $pendingNotFoundHold
                                                && \App\Jobs\SendWebhookJob::isScreenshotIssueManualReason($latestDelivery->manual_reason ?? null);
                                            $legacyPendingProductionWebhook = $hasPendingProductionWebhook
                                                && ! $pendingWebhookNeedsManualOutcome
                                                && ! $pendingNotFoundHold
                                                && ! $pendingScreenshotIssue;
                                            $canSendErrorWebhook = ! empty($company->webhook_url)
                                                && $verification->status === 'error'
                                                && $verification->latestVerificationResult !== null
                                                && ! (bool) ($verification->succeeded_webhook_exists ?? false);
                                        @endphp
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <a
                                                    href="{{ route('dashboard.verifications.show', $verification->id) }}"
                                                    class="text-indigo-700 hover:text-indigo-900 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 rounded-sm"
                                                    title="{{ __('Open details') }}"
                                                >
                                                    #{{ $verification->id }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $verification->license_type }} - {{ $verification->license_state }} - {{ $verification->license_number }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <a
                                                    href="{{ route('dashboard.verifications.show', $verification->id) }}"
                                                    class="hover:underline"
                                                    title="{{ __('Open details') }}"
                                                >
                                                    {{ $verification->full_name ?? ($verification->first_name . ' ' . $verification->last_name) }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $verification->npi ?? '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @php
                                                    $latestResult = $verification->latestVerificationResult;
                                                    $tooltipLines = [];
                                                    if ($latestResult) {
                                                        $tooltipLines[] = 'Success: '.($latestResult->success ? 'true' : 'false');
                                                        if ($latestResult->provider_status_code) {
                                                            $tooltipLines[] = 'Provider status: '.$latestResult->provider_status_code;
                                                        }
                                                        if (!empty($latestResult->error_code)) {
                                                            $tooltipLines[] = 'Error code: '.$latestResult->error_code;
                                                        }
                                                        if (!empty($latestResult->error_message)) {
                                                            $tooltipLines[] = 'Message: '.$latestResult->error_message;
                                                        }
                                                        if ($latestResult->created_at) {
                                                            $tooltipLines[] = 'Result: '.$latestResult->created_at->toDateTimeString();
                                                        }
                                                    } else {
                                                        $tooltipLines[] = 'No result yet';
                                                    }
                                                    if (!empty($verification->verified_from)) {
                                                        $tooltipLines[] = 'Verified from: '.$verification->verified_from;
                                                    }
                                                    if (!empty($verification->last_verification_source)) {
                                                        $tooltipLines[] = 'Source: '.$verification->last_verification_source;
                                                    }
                                                    if ($latestDelivery && $latestDelivery->manual_check_required && $latestDelivery->succeeded_at === null) {
                                                        $tooltipLines[] = 'Manual review required';
                                                        if (!empty($latestDelivery->manual_reason)) {
                                                            $tooltipLines[] = 'Manual reason: '.$latestDelivery->manual_reason;
                                                        }
                                                        // Submitted vs board record name — uses eager-loaded models only (no extra queries).
                                                        $submittedNameTrim = trim((string) ($verification->full_name ?: trim(($verification->first_name ?? '').' '.($verification->last_name ?? ''))));
                                                        if ($submittedNameTrim !== '') {
                                                            $tooltipLines[] = 'Submitted name: '.\Illuminate\Support\Str::limit($submittedNameTrim, 200);
                                                        }
                                                        $submittedLicTrim = trim(trim(($verification->license_type ?? '').' '.($verification->license_state ?? '').' '.($verification->license_number ?? '')));
                                                      
                                                        if ($latestResult) {
                                                            $pp = $manualReviewProviderPayloads[$verification->id] ?? null;
                                                            $payloadRow = [];
                                                            if (is_array($pp)) {
                                                                $nested = $pp['data']['data'] ?? null;
                                                                $payloadRow = is_array($nested)
                                                                    ? $nested
                                                                    : (is_array($pp['data'] ?? null) ? $pp['data'] : []);
                                                            }
                                                            $receivedName = '';
                                                            if (! empty($payloadRow['name']) && is_scalar($payloadRow['name'])) {
                                                                $receivedName = trim((string) $payloadRow['name']);
                                                            }
                                                            if ($receivedName === '' && (! empty($payloadRow['first_name']) || ! empty($payloadRow['last_name']))) {
                                                                $receivedName = trim(((string) ($payloadRow['first_name'] ?? '')).' '.((string) ($payloadRow['last_name'] ?? '')));
                                                            }
                                                            $meta = [];
                                                            if (is_array($pp)) {
                                                                $meta = is_array($pp['data']['meta'] ?? null)
                                                                    ? $pp['data']['meta']
                                                                    : (is_array($pp['meta'] ?? null) ? $pp['meta'] : []);
                                                            }
                                                            foreach (['board_full_name', 'record_full_name', 'provider_record_full_name'] as $_mfKey) {
                                                                if ($receivedName === '' && ! empty($meta[$_mfKey]) && is_scalar($meta[$_mfKey])) {
                                                                    $receivedName = trim((string) $meta[$_mfKey]);
                                                                    break;
                                                                }
                                                            }
                                                            $tooltipLines[] = 'Board name: '.($receivedName !== ''
                                                                ? \Illuminate\Support\Str::limit($receivedName, 200)
                                                                : '—');
                                                        }
                                                    }
                                                    if ($latestDelivery && $latestDelivery->pending_not_found_hold && $latestDelivery->succeeded_at === null) {
                                                        $tooltipLines[] = 'Not found production webhook held (settings) — not sent to client yet';
                                                    }
                                                    if ($pendingScreenshotIssue) {
                                                        $tooltipLines[] = 'Screenshot issue: webhook held — screenshot could not be downloaded or converted to base64.';
                                                        if (!empty($latestDelivery->manual_reason)) {
                                                            $tooltipLines[] = 'Screenshot detail: '.$latestDelivery->manual_reason;
                                                        }
                                                    }
                                                    if (
                                                        $verification->status === \App\Models\LicenseVerification::STATUS_WAITING_PROVIDER
                                                        && !empty($verification->code)
                                                    ) {
                                                        $providerInfo = $providerStatusByCode[$verification->code] ?? null;
                                                        if (is_array($providerInfo)) {
                                                            if (!empty($providerInfo['last_error_code'])) {
                                                                $tooltipLines[] = 'Provider down code: '.$providerInfo['last_error_code'];
                                                            }
                                                            if (!empty($providerInfo['last_error'])) {
                                                                $tooltipLines[] = 'Provider down reason: '.$providerInfo['last_error'];
                                                            }
                                                            if (!empty($providerInfo['last_error_verification_id'])) {
                                                                $tooltipLines[] = 'Triggered by license ID: #'.$providerInfo['last_error_verification_id'];
                                                            }
                                                            if (!empty($providerInfo['last_checked_at'])) {
                                                                $tooltipLines[] = 'Provider checked at: '.$providerInfo['last_checked_at'];
                                                            }
                                                        }
                                                    }
                                                    $tooltipText = implode("\n", $tooltipLines);
                                                @endphp
                                                <span class="relative inline-flex group">
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                            @if($verification->status === 'verified') bg-green-100 text-green-800
                                                            @elseif($verification->status === 'pending' || $verification->status === 'verifying') bg-yellow-100 text-yellow-800
                                                            @elseif($verification->status === \App\Models\LicenseVerification::STATUS_WAITING_PROVIDER) bg-amber-100 text-amber-900
                                                            @elseif($verification->status === 'error') bg-red-100 text-red-800
                                                            @else bg-gray-100 text-gray-800
                                                            @endif"
                                                    >
                                                        {{ $verification->statusLabel() }}
                                                    </span>
                                                    @if($latestDelivery && $latestDelivery->manual_check_required && $latestDelivery->succeeded_at === null)
                                                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800" title="{{ \Illuminate\Support\Str::limit((string) ($latestDelivery->manual_reason ?: __('Manual review — hover status cell for details')), 140) }}">
                                                            Manual Review
                                                        </span>
                                                    @endif
                                                    @if($pendingNotFoundHold && ! $pendingWebhookNeedsManualOutcome)
                                                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-sky-100 text-sky-900" title="{{ __('Held not_found — send from Actions when ready') }}">
                                                            {{ __('NF pending') }}
                                                        </span>
                                                    @endif
                                                    @if($pendingScreenshotIssue)
                                                        <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-rose-100 text-rose-900" title="{{ __('Screenshot issue — webhook held; screenshot could not be downloaded or converted') }}">
                                                            {{ __('Screenshot issue') }}
                                                        </span>
                                                    @endif
                                                    <span
                                                        class="pointer-events-none absolute z-20 hidden group-hover:block group-focus-within:block
                                                            left-0 top-full mt-2 w-[24rem] max-w-[90vw]
                                                            whitespace-pre-line rounded-md border border-gray-200 bg-white p-3 text-xs text-gray-900 shadow-lg"
                                                        role="tooltip"
                                                    >
                                                        {{ $tooltipText }}
                                                    </span>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600 tabular-nums">
                                                @php
                                                    // Lifetime per-outcome attempt counts (from verification_results aggregates).
                                                    // These persist across status flips, unlike the live retry-scheduler counters
                                                    // (error_retry_count / not_found_retry_count) which reset on success.
                                                    $vAttempts  = (int) ($verification->verified_attempts ?? 0);
                                                    $errAttempts = (int) ($verification->error_attempts ?? 0);
                                                    $nfAttempts = (int) ($verification->not_found_attempts ?? 0);
                                                    $mmAttempts = (int) ($verification->mismatch_attempts ?? 0);
                                                    $totalAttempts = $vAttempts + $errAttempts + $nfAttempts + $mmAttempts;
                                                @endphp
                                                <span title="Total provider calls (lifetime)" class="font-medium text-gray-900">T: {{ $totalAttempts }}</span>
                                                <span class="mx-1 text-gray-300">|</span>
                                                <span title="Verified attempts (lifetime)" class="text-emerald-600">V: {{ $vAttempts }}</span>
                                                <span class="mx-1 text-gray-300">|</span>
                                                <span title="Error attempts — transient/infra (lifetime)" class="text-red-600">E: {{ $errAttempts }}</span>
                                                <span class="mx-1 text-gray-300">|</span>
                                                <span title="Not-found attempts (lifetime)" class="text-amber-600">NF: {{ $nfAttempts }}</span>
                                                <span class="mx-1 text-gray-300">|</span>
                                                <span title="Mismatch attempts (lifetime)" class="text-purple-600">MM: {{ $mmAttempts }}</span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ optional($verification->updated_at)->diffForHumans() }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                @if($canReverify)
                                                    <form method="POST" action="{{ route('dashboard.verifications.reverify', $verification->id) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="inline-flex items-center px-2.5 py-1 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                                            Reverify
                                                        </button>
                                                    </form>
                                                    @if($pendingWebhookNeedsManualOutcome)
                                                        <button
                                                            type="button"
                                                            class="js-open-send-webhook-modal inline-flex items-center px-2.5 py-1 border border-emerald-300 text-xs font-medium rounded-md text-emerald-800 bg-white hover:bg-emerald-50 ml-1"
                                                            data-send-url="{{ route('dashboard.verifications.send-webhook', $verification->id) }}"
                                                            data-requires-manual-outcome="1"
                                                        >
                                                            {{ __('Send Webhook') }}
                                                        </button>
                                                    @elseif($pendingNotFoundHold)
                                                        <form method="POST" action="{{ route('dashboard.verifications.send-not-found-webhook', $verification->id) }}" class="inline ml-1">
                                                            @csrf
                                                            <button type="submit" class="inline-flex items-center px-2.5 py-1 border border-sky-400 text-xs font-medium rounded-md text-sky-900 bg-white hover:bg-sky-50">
                                                                {{ __('Send NF') }}
                                                            </button>
                                                        </form>
                                                    @elseif($legacyPendingProductionWebhook)
                                                        <button
                                                            type="button"
                                                            class="js-open-send-webhook-modal inline-flex items-center px-2.5 py-1 border border-emerald-300 text-xs font-medium rounded-md text-emerald-800 bg-white hover:bg-emerald-50 ml-1"
                                                            data-send-url="{{ route('dashboard.verifications.send-webhook', $verification->id) }}"
                                                            data-requires-manual-outcome="0"
                                                        >
                                                            {{ __('Send Webhook') }}
                                                        </button>
                                                    @elseif($pendingScreenshotIssue)
                                                        <button
                                                            type="button"
                                                            class="js-open-send-webhook-modal inline-flex items-center px-2.5 py-1 border border-rose-300 text-xs font-medium rounded-md text-rose-800 bg-white hover:bg-rose-50 ml-1"
                                                            data-send-url="{{ route('dashboard.verifications.send-webhook', $verification->id) }}"
                                                            data-requires-manual-outcome="0"
                                                        >
                                                            {{ __('Send Webhook') }}
                                                        </button>
                                                    @elseif($canSendErrorWebhook)
                                                        <button
                                                            type="button"
                                                            class="js-open-error-row-webhook-modal inline-flex items-center px-2.5 py-1 border border-red-300 text-xs font-medium rounded-md text-red-900 bg-white hover:bg-red-50 ml-1"
                                                            data-send-url="{{ route('dashboard.verifications.send-error-webhook', $verification->id) }}"
                                                        >
                                                            {{ __('Send error webhook') }}
                                                        </button>
                                                    @endif
                                                @else
                                                    <span class="text-xs text-gray-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="mt-4 border-t border-gray-200 pt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-2">
                            <input type="hidden" name="license_state" value="{{ $activeLicenseState }}">
                            <input type="hidden" name="license_type" value="{{ $activeLicenseType ?? '' }}">
                            <input type="hidden" name="filter" value="{{ $activeFilter }}">
                            @if ($manualReviewOnly ?? false)
                                <input type="hidden" name="manual_review_only" value="1">
                            @endif
                            @if ($nppesOnlyActive)
                                <input type="hidden" name="nppes_only" value="1">
                            @endif
                            <input type="hidden" name="search" value="{{ $search }}">
                            <input type="hidden" name="created_from" value="{{ $createdFromInput }}">
                            <input type="hidden" name="created_to" value="{{ $createdToInput }}">
                            <label for="per_page" class="text-sm text-gray-600">{{ __('Per page') }}:</label>
                            <select
                                id="per_page"
                                name="per_page"
                                class="text-sm border-gray-300 rounded-md shadow-sm"
                                onchange="this.form.submit()"
                            >
                                @foreach ([15, 25, 50, 100] as $n)
                                    <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                {{ __('Apply') }}
                            </button>
                        </form>
                        <div class="min-w-0 sm:flex-1 sm:flex sm:justify-end">
                            @if ($verifications->hasPages())
                                {{ $verifications->onEachSide(1)->links() }}
                            @elseif (! $verifications->isEmpty())
                                <p class="text-sm text-gray-600">
                                    {{ __('Showing :first to :last of :total results', [
                                        'first' => $verifications->firstItem(),
                                        'last' => $verifications->lastItem(),
                                        'total' => $verifications->total(),
                                    ]) }}
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
