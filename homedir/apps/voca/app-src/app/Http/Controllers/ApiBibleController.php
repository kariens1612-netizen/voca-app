<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ApiBibleController extends Controller
{
    public function bibles(Request $request)
    {
        return $this->proxy('bibles', $request->only(['language', 'abbreviation']));
    }

    public function books(Request $request, string $bible)
    {
        return $this->proxy("bibles/{$this->cleanId($bible)}/books", [
            'include-chapters' => $request->boolean('include_chapters', true) ? 'true' : 'false',
        ]);
    }

    public function chapters(string $bible, string $book)
    {
        return $this->proxy("bibles/{$this->cleanId($bible)}/books/{$this->cleanId($book)}/chapters");
    }

    public function chapter(Request $request, string $bible, string $chapter)
    {
        return $this->proxy("bibles/{$this->cleanId($bible)}/chapters/{$this->cleanId($chapter)}", [
            'content-type' => 'html',
            'include-notes' => $request->boolean('include_notes') ? 'true' : 'false',
            'include-titles' => 'true',
            'include-chapter-numbers' => 'true',
            'include-verse-numbers' => 'true',
        ]);
    }

    public function passage(Request $request, string $bible)
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:120'],
        ]);

        $passage = Str::of($data['query'])
            ->replaceMatches('/[^A-Za-z0-9 .,:;-]/', '')
            ->squish()
            ->toString();

        return $this->proxy("bibles/{$this->cleanId($bible)}/passages/".rawurlencode($passage), [
            'content-type' => 'html',
            'include-notes' => 'false',
            'include-titles' => 'true',
            'include-chapter-numbers' => 'true',
            'include-verse-numbers' => 'true',
        ]);
    }

    private function proxy(string $path, array $query = [])
    {
        $key = config('services.api_bible.key');

        if (blank($key)) {
            return response()->json([
                'message' => 'API.Bible is not configured. Add API_BIBLE_KEY to the Laravel environment.',
            ], 503);
        }

        $path = trim($path, '/');
        $query = array_filter($query, fn ($value) => filled($value));
        $cacheKey = 'api-bible:'.sha1($path.'?'.http_build_query($query));

        try {
            $payload = Cache::remember($cacheKey, now()->addHours(12), function () use ($key, $path, $query) {
                $response = Http::baseUrl(rtrim(config('services.api_bible.base_url'), '/'))
                    ->acceptJson()
                    ->withHeader('api-key', $key)
                    ->timeout(12)
                    ->get($path, $query);

                return [
                    'status' => $response->status(),
                    'body' => $response->json() ?: ['message' => $response->body()],
                ];
            });
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'API.Bible could not be reached. Please try again shortly.',
            ], 502);
        }

        return response()->json($payload['body'], $payload['status']);
    }

    private function cleanId(string $id): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]/', '', $id);
    }
}
