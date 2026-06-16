<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Verification #{{ $verification->id }} Details
            </h2>
            <a href="{{ route('dashboard', request()->query()) }}" class="text-sm text-blue-600 hover:text-blue-800">
                ← Back to dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div><span class="font-semibold text-gray-700">Request ID:</span> {{ $verification->request_uuid }}</div>
                    <div><span class="font-semibold text-gray-700">Status:</span> {{ $verification->statusLabel() }}</div>
                    <div><span class="font-semibold text-gray-700">License:</span> {{ $verification->license_type }} - {{ $verification->license_state }} - {{ $verification->license_number }}</div>
                    <div><span class="font-semibold text-gray-700">NPI:</span> {{ $verification->npi ?? '-' }}</div>
                    <div><span class="font-semibold text-gray-700">Name:</span> {{ $verification->full_name ?? trim(($verification->first_name ?? '').' '.($verification->last_name ?? '')) }}</div>
                    <div><span class="font-semibold text-gray-700">Updated:</span> {{ optional($verification->updated_at)->toDateTimeString() }}</div>
                </div>
            </div>

            @if(($hasPendingProductionWebhook ?? false) && isset($latestDelivery) && $latestDelivery)
                @if($pendingWebhookNeedsManualOutcome ?? false)
                    <div class="bg-purple-50 border border-purple-200 text-purple-900 rounded-lg p-4">
                        <div class="flex flex-col gap-3">
                            <div>
                                <div class="text-sm font-semibold">{{ __('Manual review required before production webhook send.') }}</div>
                                <div class="text-xs mt-1">
                                    @if(!empty($latestDelivery->manual_reason))
                                        {{ __('Reason') }}: {{ $latestDelivery->manual_reason }}
                                    @else
                                        {{ __('Reason') }}: —
                                    @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('dashboard.verifications.send-webhook', $verification->id) }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @csrf
                                <div class="md:col-span-2">
                                    <label for="manual_outcome" class="block text-xs font-medium text-purple-950 mb-1">{{ __('License outcome') }}</label>
                                    <select name="manual_outcome" id="manual_outcome" required class="w-full text-sm border-gray-300 rounded-md shadow-sm">
                                        <option value="" disabled @selected(! old('manual_outcome'))>{{ __('Select…') }}</option>
                                        <option value="verified_active" @selected(old('manual_outcome') === 'verified_active')>{{ __('Verified — active') }}</option>
                                        <option value="verified_expired" @selected(old('manual_outcome') === 'verified_expired')>{{ __('Verified — expired / lapsed') }}</option>
                                        <option value="not_found" @selected(old('manual_outcome') === 'not_found')>{{ __('Not found') }}</option>
                                        <option value="mismatch" @selected(old('manual_outcome') === 'mismatch')>{{ __('Mismatch') }}</option>
                                    </select>
                                    @error('manual_outcome')
                                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div id="detail-mismatch-dimension-wrap" class="md:col-span-2 @if(old('manual_outcome') !== 'mismatch') hidden @endif">
                                    <label for="mismatch_dimension" class="block text-xs font-medium text-purple-950 mb-1">{{ __('Mismatch details') }}</label>
                                    <select name="mismatch_dimension" id="mismatch_dimension" class="w-full text-sm border-gray-300 rounded-md shadow-sm">
                                        <option value="">{{ __('Select…') }}</option>
                                        <option value="name" @selected(old('mismatch_dimension') === 'name')>{{ __('Name does not match board record') }}</option>
                                        <option value="license_type" @selected(old('mismatch_dimension') === 'license_type')>{{ __('License type / profession does not match board record') }}</option>
                                    </select>
                                    @error('mismatch_dimension')
                                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="md:col-span-2">
                                    <label for="manual_notes" class="block text-xs font-medium text-purple-950 mb-1">{{ __('Notes (Status_Notes)') }}</label>
                                    <textarea name="manual_notes" id="manual_notes" rows="3" maxlength="5000" class="w-full text-sm border-gray-300 rounded-md shadow-sm" placeholder="{{ __('Optional') }}">{{ old('manual_notes') }}</textarea>
                                    @error('manual_notes')
                                        <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="md:col-span-2">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center px-3 py-1.5 border border-emerald-300 text-xs font-medium rounded-md text-emerald-900 bg-white hover:bg-emerald-50"
                                    >
                                        {{ __('Send Webhook') }}
                                    </button>
                                </div>
                            </form>
                            <script>
                                (function () {
                                    var out = document.getElementById('manual_outcome');
                                    var wrap = document.getElementById('detail-mismatch-dimension-wrap');
                                    var dim = document.getElementById('mismatch_dimension');
                                    if (! out || ! wrap || ! dim) return;
                                    function sync() {
                                        var show = out.value === 'mismatch';
                                        wrap.classList.toggle('hidden', ! show);
                                        if (show) dim.setAttribute('required', 'required');
                                        else dim.removeAttribute('required');
                                    }
                                    out.addEventListener('change', sync);
                                    sync();
                                })();
                            </script>
                        </div>
                    </div>
                @elseif($pendingNotFoundHoldSend ?? false)
                    <div class="bg-sky-50 border border-sky-200 text-sky-950 rounded-lg p-4">
                        <div class="text-sm font-semibold">{{ __('Not found production webhook held') }}</div>
                        <p class="text-xs mt-2 text-sky-900">
                            {{ __('Your settings queue not_found results until you send. Uses the same automatic payload as the verification job.') }}
                        </p>
                        <form method="POST" action="{{ route('dashboard.verifications.send-not-found-webhook', $verification->id) }}" class="mt-3">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center px-3 py-1.5 border border-sky-400 text-xs font-medium rounded-md text-sky-950 bg-white hover:bg-sky-100"
                            >
                                {{ __('Send not_found webhook') }}
                            </button>
                        </form>
                    </div>
                @elseif($showLegacyPendingProductionSend ?? false)
                    <div class="bg-amber-50 border border-amber-200 text-amber-950 rounded-lg p-4">
                        <div class="text-sm font-semibold">{{ __('Production webhook not yet delivered') }}</div>
                        <p class="text-xs mt-2 text-amber-900">
                            {{ __('Send using the same automatic payload as the verification job (no manual outcome step).') }}
                        </p>
                        <form method="POST" action="{{ route('dashboard.verifications.send-webhook', $verification->id) }}" class="mt-3">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center px-3 py-1.5 border border-amber-400 text-xs font-medium rounded-md text-amber-950 bg-white hover:bg-amber-100"
                            >
                                {{ __('Send production webhook') }}
                            </button>
                        </form>
                    </div>
                @endif
            @endif

            @php
                $canSeeCreatePayload =
                    app()->environment(['local', 'development', 'dev'])
                    || in_array(optional(auth()->user())->email, [
                        'developer@valyd.id',
                        'dev@valyd.id',
                    ], true);
            @endphp

                @php
                    $createPayload = [
                        'code' => $verification->code,
                        'license_type' => $verification->license_type,
                        'license_state' => $verification->license_state,
                        'license_number' => $verification->license_number,
                    ];

                    if (! empty($verification->full_name)) {
                        $createPayload['full_name'] = $verification->full_name;
                    } else {
                        if (! empty($verification->first_name)) {
                            $createPayload['first_name'] = $verification->first_name;
                        }
                        if (! empty($verification->last_name)) {
                            $createPayload['last_name'] = $verification->last_name;
                        }
                    }

                    foreach (['npi', 'city', 'zip', 'state', 'category'] as $optField) {
                        if (! empty($verification->{$optField})) {
                            $createPayload[$optField] = $verification->{$optField};
                        }
                    }
                @endphp

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-2">
                            <h3 class="text-lg font-semibold text-gray-900">Request Payload (create)</h3>
                            <button
                                type="button"
                                id="copyCreatePayloadBtn"
                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Copy JSON
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mb-3">
                            Copy/paste this into your API test client for <code class="bg-gray-100 px-1 rounded">POST /api/v1/license-verifications</code>.
                        </p>
                        <textarea
                            id="createPayloadTextarea"
                            rows="12"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 font-mono text-xs"
                            spellcheck="false"
                        >{{ json_encode($createPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                    </div>
                </div>

            @php
                $stats = $attemptStats ?? [
                    'total' => 0, 'verified' => 0, 'not_found' => 0,
                    'mismatch' => 0, 'error' => 0,
                    'first_attempt_at' => null, 'last_attempt_at' => null,
                ];
                $timelineRows = $timeline ?? collect();
            @endphp

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                        <h3 class="text-lg font-semibold text-gray-900">Attempt History</h3>
                        <div class="text-xs text-gray-600 tabular-nums">
                            @if($stats['total'] > 0)
                                <span class="font-medium text-gray-900">T: {{ $stats['total'] }}</span>
                                <span class="mx-1 text-gray-300">|</span>
                                <span class="text-emerald-600">V: {{ $stats['verified'] }}</span>
                                <span class="mx-1 text-gray-300">|</span>
                                <span class="text-red-600">E: {{ $stats['error'] }}</span>
                                <span class="mx-1 text-gray-300">|</span>
                                <span class="text-amber-600">NF: {{ $stats['not_found'] }}</span>
                                <span class="mx-1 text-gray-300">|</span>
                                <span class="text-purple-600">MM: {{ $stats['mismatch'] }}</span>
                            @endif
                        </div>
                    </div>
                    @if($stats['total'] > 0 && $stats['first_attempt_at'])
                        <p class="text-xs text-gray-500 mb-3">
                            First: {{ optional($stats['first_attempt_at'])->toDateTimeString() }}
                            &middot;
                            Last: {{ optional($stats['last_attempt_at'])->toDateTimeString() }}
                        </p>
                    @endif

                    @if($timelineRows->isEmpty())
                        <p class="text-gray-500 text-sm">No provider calls recorded yet.</p>
                    @else
                        <div class="overflow-x-auto border border-gray-200 rounded-md">
                            <table class="min-w-full divide-y divide-gray-200 text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">#</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">When</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Outcome</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">HTTP</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Error Code</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Source</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    @foreach($timelineRows as $i => $row)
                                        @php
                                            $badge = match ($row['outcome']) {
                                                'verified' => 'bg-emerald-100 text-emerald-800',
                                                'not_found' => 'bg-amber-100 text-amber-800',
                                                'mismatch' => 'bg-purple-100 text-purple-800',
                                                default => 'bg-red-100 text-red-800',
                                            };
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2 text-gray-500 tabular-nums">{{ $i + 1 }}</td>
                                            <td class="px-3 py-2 text-gray-700 tabular-nums whitespace-nowrap">
                                                {{ optional($row['created_at'])->toDateTimeString() }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $badge }}">
                                                    {{ str_replace('_', ' ', $row['outcome']) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-gray-700 tabular-nums">{{ $row['provider_status_code'] ?? '-' }}</td>
                                            <td class="px-3 py-2 text-gray-700">{{ $row['error_code'] ?? '-' }}</td>
                                            <td class="px-3 py-2 text-gray-700">{{ $row['verified_from'] ?? '-' }}</td>
                                            <td class="px-3 py-2 text-gray-600 max-w-md truncate" title="{{ $row['error_message'] ?? '' }}">
                                                {{ $row['error_message'] ?? '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Latest Verification Response</h3>

                    @if(!$latestResult)
                        <p class="text-gray-500 text-sm">No verification response found yet.</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 text-sm">
                            <div><span class="font-semibold text-gray-700">Success:</span> {{ $latestResult->success ? 'true' : 'false' }}</div>
                            <div><span class="font-semibold text-gray-700">Provider Status:</span> {{ $latestResult->provider_status_code ?? '-' }}</div>
                            <div><span class="font-semibold text-gray-700">Result Time:</span> {{ optional($latestResult->created_at)->toDateTimeString() }}</div>
                        </div>

                        <div class="mb-4 text-sm">
                            <span class="font-semibold text-gray-700">Error Code:</span> {{ $latestResult->error_code ?? '-' }}
                            <br>
                            <span class="font-semibold text-gray-700">Error Message:</span> {{ $latestResult->error_message ?? '-' }}
                            @if(isset($latestDelivery) && $latestDelivery && !empty($latestDelivery->manual_reason))
                                <br>
                                <span class="font-semibold text-gray-700">Manual Review Reason:</span> {{ $latestDelivery->manual_reason }}
                            @endif
                        </div>

                        <h4 class="font-semibold text-gray-800 mb-2">Provider Payload (raw)</h4>
                        <pre class="bg-gray-900 text-gray-100 p-4 rounded-lg overflow-x-auto text-xs">{{ json_encode($latestResult->provider_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($canSeeCreatePayload ?? false)
        <script>
            (function () {
                var btn = document.getElementById('copyCreatePayloadBtn');
                var ta = document.getElementById('createPayloadTextarea');
                if (!btn || !ta) return;

                btn.addEventListener('click', async function () {
                    try {
                        await navigator.clipboard.writeText(ta.value);
                        var original = btn.textContent;
                        btn.textContent = 'Copied!';
                        setTimeout(function () { btn.textContent = original; }, 1500);
                    } catch (e) {
                        // Fallback for older browsers
                        ta.focus();
                        ta.select();
                        document.execCommand('copy');
                    }
                });
            })();
        </script>
    @endif
</x-app-layout>
