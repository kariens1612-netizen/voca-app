@php
    $ministryName = $ministry?->name;
@endphp

<h2 style="margin:0 0 12px;">{{ $announcement->title }}</h2>

@if($ministryName)
<p style="margin:0 0 12px; color:#475569;">From {{ $ministryName }}</p>
@endif

<p style="white-space: pre-wrap; color:#1f2937;">{{ $announcement->body }}</p>

<p style="margin-top:20px; color:#64748b; font-size:12px;">
    Sent {{ $announcement->created_at?->toDayDateTimeString() }}
</p>
