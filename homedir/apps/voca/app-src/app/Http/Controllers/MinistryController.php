<?php

namespace App\Http\Controllers;

use App\Models\Ministry;
use Illuminate\Http\Request;

class MinistryController extends Controller
{
    public function tenantIndex(Request $request)
    {
        $ministries = Ministry::query()->orderBy('name')->get()->map(fn (Ministry $ministry) => $this->payload($ministry))->values();
        return response()->json(['entity' => $request->attributes->get('organisation')->slug, 'ministries' => $ministries]);
    }

    public function tenantShow(Request $request, Ministry $ministry)
    {
        abort_unless((int) $ministry->organisation_id === (int) $request->attributes->get('organisation')->id, 404);
        return response()->json($this->payload($ministry));
    }

    public function index(Request $request, string $entity)
    {
        $ministries = Ministry::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Ministry $ministry) => $this->payload($ministry))
            ->values();

        return response()->json([
            'entity' => $entity,
            'ministries' => $ministries,
        ]);
    }

    public function show(Request $request, string $entity, Ministry $ministry)
    {
        return response()->json($this->payload($ministry));
    }

    protected function payload(Ministry $ministry): array
    {
        return [
            'id' => $ministry->id,
            'entity' => $ministry->entity,
            'slug' => $ministry->slug,
            'name' => $ministry->name,
            'description' => $ministry->description,
            'whatsapp_number' => $ministry->whatsapp_number,
            'details' => $ministry->details,
            'theme' => $ministry->theme,
        ];
    }
}
