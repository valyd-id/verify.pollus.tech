Provider DOWN alert

Company: {{ $companyName !== '' ? $companyName : '—' }}
@if (! empty($companyAlertEmail))
Company alert email: {{ $companyAlertEmail }}
@endif
Internal reference ID: {{ $companyId }}

Codes transitioned to DOWN:
@foreach ($downItems as $item)
- Code: {{ $item['Code'] ?? '' }}
  Failure count: {{ $item['Meta']['failure_count'] ?? '' }}
  Previous status: {{ $item['Meta']['previous_status'] ?? '' }}
  Last error: {{ $item['Meta']['last_error'] ?? '' }}
@endforeach

Timestamp: {{ now()->toIso8601String() }}

