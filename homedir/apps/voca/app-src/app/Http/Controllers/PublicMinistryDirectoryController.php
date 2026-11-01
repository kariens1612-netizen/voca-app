<?php

namespace App\Http\Controllers;

use App\Models\LiveStreamSetting;
use App\Models\Ministry;
use App\Models\Organisation;
use Illuminate\Http\Request;

class PublicMinistryDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $organisations = Organisation::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $streamSettings = LiveStreamSetting::withoutGlobalScopes()
            ->whereIn('organisation_id', $organisations->pluck('id'))
            ->get()
            ->keyBy('organisation_id');

        $ministryGroups = Ministry::withoutGlobalScopes()
            ->whereIn('organisation_id', $organisations->pluck('id'))
            ->orderBy('name')
            ->get()
            ->groupBy('organisation_id');

        return response()->json([
            'data' => $organisations->map(fn (Organisation $organisation) => $this->payload(
                $organisation,
                $streamSettings->get($organisation->id),
                $ministryGroups->get($organisation->id, collect())
            ))->values(),
        ]);
    }

    public function live(Request $request)
    {
        $directory = $this->index($request)->getData(true)['data'];
        $enabled = Organisation::where('status', 'active')->get()
            ->filter(fn (Organisation $organisation) => $organisation->moduleEnabled('livestreaming'))
            ->pluck('id')->all();

        return response()->json(['data' => collect($directory)->filter(fn ($item) =>
            in_array($item['id'], $enabled, true) && $item['live']['is_live']
            && ($item['live']['embed_url'] || $item['live']['watch_url'])
        )->values()])->header('Cache-Control', 'no-store');
    }

    private function payload(Organisation $organisation, ?LiveStreamSetting $stream, $ministries): array
    {
        $branding = $organisation->branding ?? [];
        $settings = $organisation->settings ?? [];
        $watchUrl = $stream?->watch_url ?: data_get($branding, 'rumble_url') ?: data_get($settings, 'rumble_url');

        return [
            'id' => $organisation->id,
            'name' => data_get($branding, 'name') ?: $organisation->name,
            'slug' => $organisation->slug,
            'tagline' => data_get($branding, 'tagline'),
            'description' => data_get($settings, 'description')
                ?: data_get($branding, 'description')
                ?: data_get($branding, 'footer_text'),
            'logo_url' => data_get($branding, 'logo_url'),
            'thumbnail_url' => data_get($branding, 'thumbnail_url')
                ?: data_get($branding, 'banner_url')
                ?: data_get($branding, 'logo_url'),
            'primary_colour' => data_get($branding, 'primary_colour', '#bd1622'),
            'secondary_colour' => data_get($branding, 'secondary_colour', '#111827'),
            'social_links' => $this->socialLinks($branding, $settings),
            'live' => [
                'is_live' => $this->isLive($stream?->status),
                'status' => $stream?->status ?: 'Stand by',
                'platform' => $stream?->platform ?: 'Rumble',
                'schedule' => $stream?->schedule,
                'stream_title' => $stream?->stream_title,
                'embed_url' => $stream?->embed_url,
                'watch_url' => $watchUrl,
            ],
            'ministries' => $ministries->map(fn (Ministry $ministry) => [
                'name' => $ministry->name,
                'slug' => $ministry->slug,
                'description' => $ministry->description,
            ])->values(),
        ];
    }

    private function socialLinks(array $branding, array $settings): array
    {
        $links = data_get($branding, 'social_links', data_get($settings, 'social_links', []));

        if (! is_array($links)) {
            return [];
        }

        return collect($links)
            ->map(function ($url, $platform) {
                if (is_array($url)) {
                    $platform = $url['platform'] ?? $url['label'] ?? $platform;
                    $url = $url['url'] ?? null;
                }

                return [
                    'platform' => ucfirst(str_replace(['_', '-'], ' ', (string) $platform)),
                    'url' => is_string($url) ? $url : null,
                ];
            })
            ->filter(fn ($link) => filled($link['url']) && filter_var($link['url'], FILTER_VALIDATE_URL))
            ->values()
            ->all();
    }

    private function isLive(?string $status): bool
    {
        $status = strtolower((string) $status);

        return in_array(trim($status), ['live', 'live now', 'currently live', 'on air', 'broadcasting'], true);
    }
}
