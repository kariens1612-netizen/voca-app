<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class VocaDirectoryController extends Controller
{
    public function index(Request $request)
    {
        return $this->forward($request, '/api/public/directory');
    }

    public function show(string $slug)
    {
        abort_unless(preg_match('/^[a-z0-9][a-z0-9-]{0,180}$/', $slug), 404);
        return $this->forward(request(), '/api/public/directory/'.$slug);
    }

    private function forward(Request $request, string $path)
    {
        $baseUrl = rtrim((string) config('services.voca_members.url'), '/');
        abort_unless(str_starts_with($baseUrl, 'https://') || (app()->environment(['local', 'testing']) && str_starts_with($baseUrl, 'http://')), 503, 'The VOCA directory is unavailable.');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        try {
            $response = Http::acceptJson()->connectTimeout(4)->timeout(10)->withOptions(['allow_redirects' => false])
                ->get($baseUrl.$path, $filters);
        } catch (ConnectionException) {
            return response()->json(['message' => 'The VOCA business directory is temporarily unavailable.'], 503);
        }
        if ($response->status() === 404) return response()->json(['message' => 'Business listing not found.'], 404);
        if (! $response->successful()) return response()->json(['message' => 'The VOCA business directory is temporarily unavailable.'], 503);
        return response()->json($response->json())->header('Cache-Control', 'public, max-age=60');
    }
}
