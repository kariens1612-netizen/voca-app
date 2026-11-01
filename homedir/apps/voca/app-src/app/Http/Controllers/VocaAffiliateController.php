<?php
namespace App\Http\Controllers;

use App\Services\PublicAffiliateContent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class VocaAffiliateController extends Controller
{
    public function index(PublicAffiliateContent $content, ?string $slug = null)
    {
        abort_if($slug && !preg_match('/^[a-z0-9][a-z0-9-]{0,100}$/', $slug), 404);
        return $this->fetch($content, $slug ? '/affiliates/'.$slug : '/members');
    }

    public function pathway(PublicAffiliateContent $content, string $group)
    {
        abort_unless(in_array($group, ['churches-ministries', 'businesses', 'pastors', 'individuals', 'partners']), 404);
        return $this->fetch($content, '/members/'.$group);
    }

    private function fetch(PublicAffiliateContent $content, string $path)
    {
        $base = rtrim((string) config('services.voca_members.url'), '/');
        abort_unless(str_starts_with($base, 'https://') || (app()->environment(['local', 'testing']) && str_starts_with($base, 'http://')), 503);
        try {
            $data = Cache::remember('voca-affiliates:v3:'.sha1($base.$path), 300, function () use ($base, $path, $content) {
                $response = Http::connectTimeout(4)->timeout(12)->withOptions(['allow_redirects' => false])->get($base.$path);
                abort_if($response->status() === 404, 404, 'Affiliate information not found.');
                abort_unless($response->successful(), 503, 'Affiliate information is temporarily unavailable.');
                $data = $content->parse($response->body(), $base);
                abort_unless(count($data['sections']), 503, 'Affiliate information is temporarily unavailable.');
                return $data;
            });
        } catch (ConnectionException) {
            return response()->json(['message' => 'Affiliate information is temporarily unavailable. Please try again.'], 503);
        }
        return response()->json($data);
    }
}
