<?php

namespace App\Services;

use App\Models\TenantAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TenantAuditLogger
{
    public function log(Request $request, string $action, ?Model $subject = null, ?array $before = null, ?array $after = null): void
    {
        TenantAudit::create([
            'organisation_id' => $request->attributes->get('organisation')?->id,
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
