@php
    $ministryName = $ministry?->name ?? $booking->ministry;
@endphp

<h2 style="margin:0 0 12px;">New booking request</h2>

@if($ministryName)
<p style="margin:0 0 12px; color:#475569;">Ministry: {{ $ministryName }}</p>
@endif

<p style="margin:0 0 12px;"><strong>Name:</strong> {{ $booking->name }}</p>
<p style="margin:0 0 12px;"><strong>Email:</strong> {{ $booking->email }}</p>
<p style="margin:0 0 12px;"><strong>Phone:</strong> {{ $booking->phone }}</p>
<p style="margin:0 0 12px;"><strong>Date:</strong> {{ $booking->appointment_date }}</p>
<p style="margin:0 0 12px;"><strong>Time:</strong> {{ $booking->appointment_time }}</p>
<p style="margin:0 0 12px;"><strong>Amount:</strong> {{ number_format($booking->amount, 2) }}</p>

@if($booking->notes)
<p style="margin:0 0 12px;"><strong>Notes:</strong></p>
<p style="white-space: pre-wrap; color:#1f2937;">{{ $booking->notes }}</p>
@endif

<p style="margin-top:20px; color:#64748b; font-size:12px;">
    Reference {{ $booking->reference }}
</p>
