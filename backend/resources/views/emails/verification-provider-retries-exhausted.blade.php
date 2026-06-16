<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #b45309; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-top: none; }
        .alert-box { background-color: #fffbeb; border-left: 4px solid #b45309; padding: 15px; margin: 20px 0; }
        .details { background-color: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .detail-row { padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .detail-row:last-child { border-bottom: none; }
        .label { font-weight: bold; display: inline-block; width: 200px; color: #6b7280; }
        .value { color: #111827; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0;">Provider outage / transient failure</h1>
        <p style="margin: 5px 0 0 0;">Immediate retries exhausted — verification marked error</p>
    </div>

    <div class="content">
        <div class="alert-box">
            <strong>What happened:</strong> The state board / provider path failed repeatedly. All in-process immediate retries were used. The normal verification failure webhook was <strong>not</strong> sent (by design). A provider-down style notification is sent instead.
        </div>

        <div class="details">
            <h3 style="margin-top: 0;">Verification</h3>
            <div class="detail-row">
                <span class="label">Verification ID:</span>
                <span class="value">#{{ $verification->id }}</span>
            </div>
            <div class="detail-row">
                <span class="label">License:</span>
                <span class="value">{{ $verification->license_type }} — {{ $verification->license_state }} — {{ $verification->license_number }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Name:</span>
                <span class="value">{{ $verification->full_name ?? trim(($verification->first_name ?? '') . ' ' . ($verification->last_name ?? '')) }}</span>
            </div>
            @if($verification->npi)
            <div class="detail-row">
                <span class="label">NPI:</span>
                <span class="value">{{ $verification->npi }}</span>
            </div>
            @endif
            <div class="detail-row">
                <span class="label">Provider code:</span>
                <span class="value">{{ $verification->code ?? '—' }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Error code:</span>
                <span class="value">{{ $errorCode }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Error message:</span>
                <span class="value">{{ $errorMessage ?? '—' }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Provider verify calls:</span>
                <span class="value">{{ $verification->attempt_count }} / {{ $maxProviderCalls }}</span>
            </div>
            <div class="detail-row">
                <span class="label">Immediate retry chain depth:</span>
                <span class="value">{{ $immediateRetryCount }} / {{ $immediateRetryMax }}</span>
            </div>
        </div>

        <p style="font-size: 13px; color: #6b7280;">A provider availability webhook was also dispatched with <code>terminal_after_immediate_retries</code> in Meta when applicable.</p>
    </div>

    <div class="footer">
        <p>Automated message from Valyd License Verification System.</p>
        <p>Company: {{ $verification->company->name }}</p>
    </div>
</body>
</html>
