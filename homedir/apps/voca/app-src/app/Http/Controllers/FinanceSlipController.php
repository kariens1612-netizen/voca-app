<?php

namespace App\Http\Controllers;

use App\Models\FinanceSlip;
use App\Models\Ministry;
use App\Services\TenantAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FinanceSlipController extends Controller
{
    private const MAX_UPLOAD_KB = 8192;

    public function __construct(private TenantAuditLogger $audit) {}

    public function config()
    {
        return response()->json([
            'akaunting_url' => config('finance.akaunting_url'),
        ]);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['submitted', 'reviewed', 'captured', 'rejected'])],
            'ministry_id' => ['nullable', 'integer', 'exists:ministries,id'],
        ]);

        $slips = FinanceSlip::query()
            ->with(['ministry:id,name,slug,entity', 'uploader:id,name,email', 'reviewer:id,name,email'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['ministry_id'] ?? null, fn ($query, $ministryId) => $query->where('ministry_id', $ministryId))
            ->latest()
            ->paginate(50)
            ->through(fn (FinanceSlip $slip) => $slip->toPayload());

        return response()->json(['data' => $slips]);
    }

    public function store(Request $request)
    {
        $organisation = $request->attributes->get('organisation');

        $data = $request->validate([
            'ministry_id' => ['required', 'integer', 'exists:ministries,id'],
            'slip' => ['required', 'file', 'max:'.self::MAX_UPLOAD_KB, 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'purchased_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $ministry = Ministry::query()->findOrFail($data['ministry_id']);
        abort_unless((int) $ministry->organisation_id === (int) $organisation?->id, 404);
        $this->authorizeSlipUpload($request, $ministry);

        $file = $data['slip'];
        $path = $file->store("finance-slips/{$organisation->id}/{$ministry->id}", 'public');

        $slip = FinanceSlip::create([
            'organisation_id' => $organisation->id,
            'ministry_id' => $ministry->id,
            'uploaded_by' => $request->user()?->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'supplier' => $data['supplier'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => strtoupper($data['currency'] ?? 'ZAR'),
            'purchased_at' => $data['purchased_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'submitted',
        ])->load(['ministry:id,name,slug,entity', 'uploader:id,name,email']);

        $this->audit->log($request, 'finance_slip.submitted', $slip, null, $slip->toArray());

        return response()->json(['data' => $slip->toPayload()], 201);
    }

    public function update(Request $request, FinanceSlip $financeSlip)
    {
        abort_unless((int) $financeSlip->organisation_id === (int) $request->attributes->get('organisation')?->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['submitted', 'reviewed', 'captured', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $before = $financeSlip->toArray();
        $financeSlip->fill([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $financeSlip->notes,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->log($request, 'finance_slip.updated', $financeSlip, $before, $financeSlip->toArray());

        return response()->json(['data' => $financeSlip->fresh(['ministry', 'uploader', 'reviewer'])->toPayload()]);
    }

    public function destroy(Request $request, FinanceSlip $financeSlip)
    {
        abort_unless((int) $financeSlip->organisation_id === (int) $request->attributes->get('organisation')?->id, 404);

        $before = $financeSlip->toArray();
        Storage::disk($financeSlip->disk)->delete($financeSlip->path);
        $financeSlip->delete();

        $this->audit->log($request, 'finance_slip.deleted', $financeSlip, $before, null);

        return response()->noContent();
    }

    private function authorizeSlipUpload(Request $request, Ministry $ministry): void
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($user->is_superadmin) {
            return;
        }

        $organisation = $request->attributes->get('organisation');
        if ($user->organisationMemberships()
            ->where('organisation_id', $organisation?->id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin'])
            ->exists()) {
            return;
        }

        abort_unless($user->memberships()
            ->where('ministry_id', $ministry->id)
            ->exists(), 403);
    }
}
