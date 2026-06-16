<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Provider Probe Exhausted</title>
</head>
<body>
    <h2>Provider Probe Exhausted</h2>

    <p>
        Auto health probe reached the maximum failed attempts for provider code
        <strong>{{ $code }}</strong>.
    </p>

    <ul>
        <li>Failure count: {{ $failureCount }} / {{ $maxFailures }}</li>
        <li>Error code: {{ $errorCode !== '' ? $errorCode : 'UNKNOWN' }}</li>
        <li>Error message: {{ $errorMessage !== '' ? $errorMessage : 'N/A' }}</li>
        <li>DOWN companies: {{ implode(', ', $downCompanyIds) !== '' ? implode(', ', $downCompanyIds) : 'N/A' }}</li>
    </ul>

    <p>
        Probing for this code is paused because max failures were reached.
        A successful manual recovery/reset is required to resume probe attempts.
    </p>
</body>
</html>
