<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #dc2626;
            color: white;
            padding: 20px;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        .alert-box {
            background-color: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 15px;
            margin: 20px 0;
        }
        .details {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .detail-row {
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 180px;
            color: #6b7280;
        }
        .value {
            color: #111827;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="margin: 0;">⚠️ NPPES Verification Alert</h1>
        <p style="margin: 5px 0 0 0;">Max Retry Attempts Reached</p>
    </div>
    
    <div class="content">
        <div class="alert-box">
            <strong>Action Required:</strong> A license verification has reached the maximum number of retry attempts and is still being verified through NPPES fallback instead of the original state provider.
        </div>

        <p><strong>What This Means:</strong></p>
        <ul>
            <li>The license has been verified {{ $maxAttempts }} times</li>
            <li>All verifications returned NPPES (National Plan and Provider Enumeration System) data</li>
            <li>No direct state board/provider verification was obtained</li>
            <li>The record remains in "pending" status and is not visible in the system</li>
            <li>No webhook has been sent to your endpoint</li>
        </ul>

        <div class="details">
            <h3 style="margin-top: 0;">Verification Details</h3>
            
            <div class="detail-row">
                <span class="label">Verification ID:</span>
                <span class="value">#{{ $verification->id }}</span>
            </div>
            
            <div class="detail-row">
                <span class="label">License Type:</span>
                <span class="value">{{ $verification->license_type }}</span>
            </div>
            
            <div class="detail-row">
                <span class="label">License State:</span>
                <span class="value">{{ $verification->license_state }}</span>
            </div>
            
            <div class="detail-row">
                <span class="label">License Number:</span>
                <span class="value">{{ $verification->license_number }}</span>
            </div>
            
            <div class="detail-row">
                <span class="label">Name:</span>
                <span class="value">
                    {{ $verification->full_name ?? ($verification->first_name . ' ' . $verification->last_name) }}
                </span>
            </div>
            
            @if($verification->npi)
            <div class="detail-row">
                <span class="label">NPI:</span>
                <span class="value">{{ $verification->npi }}</span>
            </div>
            @endif
            
            <div class="detail-row">
                <span class="label">Total Attempts:</span>
                <span class="value">{{ $verification->nppes_retry_count }} / {{ $maxAttempts }}</span>
            </div>
            
            <div class="detail-row">
                <span class="label">Last Verified At:</span>
                <span class="value">{{ $verification->last_verified_at?->format('M d, Y g:i A') ?? 'N/A' }}</span>
            </div>
            
            <div class="detail-row">
                <span class="label">Verified From:</span>
                <span class="value" style="color: #dc2626; font-weight: bold;">{{ $verification->verified_from ?? 'NPPES Fallback' }}</span>
            </div>
        </div>

        <p><strong>Recommended Actions:</strong></p>
        <ol>
            <li><strong>Verify License Details:</strong> Confirm the license number, state, and name are correct</li>
            <li><strong>Check State Board:</strong> Manually verify on the state licensing board website</li>
            <li><strong>Contact Support:</strong> If the information is correct, the state board may have data quality issues</li>
            <li><strong>Archive Record:</strong> If this license is no longer needed, archive it to prevent future retries</li>
        </ol>

        <p style="margin-top: 25px;"><strong>Note:</strong> This verification will continue to be retried on the monthly schedule, but no additional NPPES retry attempts will be made.</p>
    </div>

    <div class="footer">
        <p>This is an automated email from Valyd License Verification System.</p>
        <p>Company: {{ $verification->company->name }}</p>
    </div>
</body>
</html>
