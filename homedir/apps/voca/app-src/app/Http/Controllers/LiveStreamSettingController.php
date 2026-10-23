<?php

namespace App\Http\Controllers;

use App\Models\LiveStreamSetting;
use App\Models\Ministry;
use Illuminate\Http\Request;

class LiveStreamSettingController extends Controller
{
    public function publicShow()
    {
        return response()->json($this->payload($this->setting()));
    }

    public function show(Request $request)
    {
        $this->authorizeStreamSettings($request);

        return response()->json($this->payload($this->setting(), true));
    }

    public function update(Request $request)
    {
        $this->authorizeStreamSettings($request);

        $data = $request->validate([
            'platform' => ['required', 'string', 'max:80'],
            'status' => ['required', 'string', 'max:80'],
            'schedule' => ['required', 'string', 'max:120'],
            'embed_url' => ['required', 'url', 'max:2000'],
            'watch_url' => ['nullable', 'url', 'max:2000'],
            'stream_title' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'api_key' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = $this->normalizeUrls($data);

        if (blank($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }

        $setting = $this->setting();
        $setting->fill($data);
        $setting->updated_by = $request->user()->id;
        $setting->save();

        return response()->json($this->payload($setting->fresh('updatedBy'), true));
    }

    protected function authorizeStreamSettings(Request $request): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->is_superadmin) {
            return;
        }

        $organisation = $request->attributes->get('organisation');
        if ($organisation && $user->organisationMemberships()->where('organisation_id', $organisation->id)
            ->where('status', 'active')->whereIn('role', ['owner', 'admin'])->exists()) {
            return;
        }

        $powerMedia = Ministry::query()
            ->where('entity', 'brackenfell')
            ->where('slug', 'power-media')
            ->first();

        if (! $powerMedia) {
            abort(404, 'Power Media ministry not found.');
        }

        $membership = $user->memberships()
            ->where('ministry_id', $powerMedia->id)
            ->with('roleRecord')
            ->first();

        if (! $membership || $membership->roleSlug() !== 'admin') {
            abort(403, 'Forbidden.');
        }
    }

    protected function setting(): LiveStreamSetting
    {
        return LiveStreamSetting::firstOrCreate(
            ['organisation_id' => app(\App\Tenancy\CurrentOrganisation::class)->id()],
            [
                'platform' => 'Rumble',
                'status' => 'Stand by',
                'schedule' => 'Sunday 09H30',
                'embed_url' => '',
                'watch_url' => 'https://rumble.com/v7cy4ji-kingdom-eyes-kingdom-victory.html',
                'stream_title' => app(\App\Tenancy\CurrentOrganisation::class)->get()?->name.' Live Stream',
                'notes' => '',
            ]
        );
    }

    protected function payload(LiveStreamSetting $setting, bool $includeSecretMetadata = false): array
    {
        $data = $this->normalizeUrls([
            'platform' => $setting->platform,
            'embed_url' => $setting->embed_url,
            'watch_url' => $setting->watch_url,
        ]);

        $payload = [
            'platform' => $setting->platform,
            'status' => $setting->status,
            'schedule' => $setting->schedule,
            'embed_url' => $data['embed_url'] ?? $setting->embed_url,
            'watch_url' => $data['watch_url'] ?? $setting->watch_url,
            'stream_title' => $setting->stream_title,
            'notes' => $setting->notes,
            'updated_by' => $setting->updatedBy?->only(['id', 'name', 'email']),
            'updated_at' => optional($setting->updated_at)?->toIso8601String(),
        ];

        if ($includeSecretMetadata) {
            $payload['has_api_key'] = filled($setting->api_key);
        }

        return $payload;
    }

    protected function normalizeUrls(array $data): array
    {
        $platform = strtolower((string) ($data['platform'] ?? ''));
        $embedUrl = $data['embed_url'] ?? null;
        $watchUrl = $data['watch_url'] ?? null;

        if (! str_contains($platform, 'odysee')) {
            return $data;
        }

        if ($embedUrl && $this->isWebUrl($embedUrl)) {
            $data['embed_url'] = $this->toOdyseeEmbedUrl($embedUrl);
        }

        if ($watchUrl && $this->isWebUrl($watchUrl)) {
            $data['watch_url'] = $this->toOdyseeWatchUrl($watchUrl);
        }

        if (! $watchUrl && ! empty($data['embed_url']) && $this->isWebUrl($data['embed_url'])) {
            $data['watch_url'] = $this->toOdyseeWatchUrl($data['embed_url']);
        }

        if (
            (! $embedUrl || ! $this->isWebUrl($embedUrl))
            && ! empty($data['watch_url'])
            && $this->isWebUrl($data['watch_url'])
        ) {
            $data['embed_url'] = $this->toOdyseeEmbedUrl($data['watch_url']);
        }

        return $data;
    }

    protected function isWebUrl(?string $url): bool
    {
        if (! $url) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true);
    }

    protected function toOdyseeEmbedUrl(string $url): string
    {
        if (! str_contains($url, 'odysee.com')) {
            return $url;
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '';

        if (str_starts_with($path, '/$/embed/')) {
            return $url;
        }

        $normalizedPath = '/$/embed' . (str_starts_with($path, '/') ? $path : '/' . $path);

        return $this->buildUrl($parts, $normalizedPath);
    }

    protected function toOdyseeWatchUrl(string $url): string
    {
        if (! str_contains($url, 'odysee.com')) {
            return $url;
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        $normalizedPath = preg_replace('#^/\$/embed#', '', $path) ?: $path;
        $normalizedPath = $normalizedPath === '' ? '/' : $normalizedPath;

        return $this->buildUrl($parts, $normalizedPath);
    }

    protected function buildUrl(array $parts, string $path): string
    {
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'odysee.com';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return "{$scheme}://{$host}{$port}{$path}{$query}{$fragment}";
    }
}
