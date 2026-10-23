<?php

namespace App\Http\Controllers;

use App\Console\Commands\SyncAimeosCommerceSites;
use App\Models\Ministry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CommerceMarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'ministry' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:96'],
        ]);

        if (! Schema::hasTable('mshop_product') || ! Schema::hasTable('mshop_locale_site')) {
            return response()->json([
                'products' => [],
                'ministries' => [],
                'status' => $this->statusPayload(false),
            ]);
        }

        $ministries = Ministry::query()
            ->with('organisation:id,slug,name')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Ministry $ministry) => [SyncAimeosCommerceSites::siteCode($ministry) => $ministry]);

        $siteCodes = $ministries->keys()->values();

        if ($siteCodes->isEmpty()) {
            return response()->json([
                'products' => [],
                'ministries' => [],
                'status' => $this->statusPayload(),
            ]);
        }

        $requestedSite = $data['ministry'] ?? null;
        if ($requestedSite && $siteCodes->contains($requestedSite)) {
            $siteCodes = collect([$requestedSite]);
        }

        $nameSubquery = DB::table('mshop_product_list as pl')
            ->join('mshop_text as t', function ($join) {
                $join->on('t.id', '=', 'pl.refid')
                    ->on('t.siteid', '=', 'pl.siteid');
            })
            ->where('pl.domain', 'text')
            ->where('pl.status', '>', 0)
            ->where('t.status', '>', 0)
            ->groupBy('pl.parentid', 'pl.siteid')
            ->selectRaw("
                pl.parentid,
                pl.siteid,
                MAX(CASE WHEN t.type = 'name' THEN t.content END) as name,
                MAX(CASE WHEN t.type IN ('short', 'long') THEN t.content END) as summary
            ");

        $priceSubquery = DB::table('mshop_product_list as pl')
            ->join('mshop_price as pr', function ($join) {
                $join->on('pr.id', '=', 'pl.refid')
                    ->on('pr.siteid', '=', 'pl.siteid');
            })
            ->where('pl.domain', 'price')
            ->where('pl.status', '>', 0)
            ->where('pr.status', '>', 0)
            ->groupBy('pl.parentid', 'pl.siteid')
            ->selectRaw('pl.parentid, pl.siteid, MIN(pr.value) as price_value, MAX(pr.currencyid) as currency');

        $mediaSubquery = DB::table('mshop_product_list as pl')
            ->join('mshop_media as m', function ($join) {
                $join->on('m.id', '=', 'pl.refid')
                    ->on('m.siteid', '=', 'pl.siteid');
            })
            ->where('pl.domain', 'media')
            ->where('pl.status', '>', 0)
            ->where('m.status', '>', 0)
            ->groupBy('pl.parentid', 'pl.siteid')
            ->selectRaw('pl.parentid, pl.siteid, MIN(m.preview) as preview, MIN(m.link) as media_link');

        $query = DB::table('mshop_product as p')
            ->leftJoinSub($nameSubquery, 'pt', function ($join) {
                $join->on('pt.parentid', '=', 'p.id')
                    ->on('pt.siteid', '=', 'p.siteid');
            })
            ->leftJoinSub($priceSubquery, 'pp', function ($join) {
                $join->on('pp.parentid', '=', 'p.id')
                    ->on('pp.siteid', '=', 'p.siteid');
            })
            ->leftJoinSub($mediaSubquery, 'pm', function ($join) {
                $join->on('pm.parentid', '=', 'p.id')
                    ->on('pm.siteid', '=', 'p.siteid');
            })
            ->whereIn('p.siteid', $siteCodes->all())
            ->where('p.status', '>', 0)
            ->where(fn ($query) => $query->whereNull('p.start')->orWhere('p.start', '<=', now()))
            ->where(fn ($query) => $query->whereNull('p.end')->orWhere('p.end', '>=', now()))
            ->when($data['search'] ?? null, function ($query, string $search) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';

                $query->where(function ($query) use ($like) {
                    $query->where('p.label', 'like', $like)
                        ->orWhere('p.code', 'like', $like)
                        ->orWhere('pt.name', 'like', $like)
                        ->orWhere('pt.summary', 'like', $like);
                });
            })
            ->orderByDesc('p.mtime')
            ->limit($data['limit'] ?? 48)
            ->select([
                'p.id',
                'p.siteid',
                'p.code',
                'p.label',
                'p.url',
                'p.rating',
                'p.ratings',
                'p.instock',
                'pt.name',
                'pt.summary',
                'pp.price_value',
                'pp.currency',
                'pm.preview',
                'pm.media_link',
            ]);

        $products = $query->get()
            ->map(fn ($product) => $this->productPayload($product, $ministries[$product->siteid] ?? null))
            ->values();

        return response()->json([
            'products' => $products,
            'ministries' => $ministries->map(fn (Ministry $ministry, string $site) => [
                'site' => $site,
                'slug' => $ministry->slug,
                'name' => $ministry->name,
                'organisation' => $ministry->organisation?->name,
            ])->values(),
            'status' => $this->statusPayload(),
        ]);
    }

    private function productPayload(object $product, ?Ministry $ministry): array
    {
        $title = $product->name ?: $product->label ?: $product->code;
        $search = rawurlencode($product->code ?: $title);

        return [
            'id' => $product->id,
            'site' => $product->siteid,
            'code' => $product->code,
            'title' => $title,
            'summary' => $product->summary ? Str::limit(strip_tags($product->summary), 180) : null,
            'price' => $product->price_value !== null ? [
                'value' => (float) $product->price_value,
                'currency' => $product->currency ?: 'USD',
            ] : null,
            'image_url' => $this->mediaUrl($product->preview ?: $product->media_link),
            'rating' => (float) $product->rating,
            'ratings' => (int) $product->ratings,
            'in_stock' => (int) $product->instock,
            'ministry' => $ministry ? [
                'slug' => $ministry->slug,
                'name' => $ministry->name,
                'site' => $product->siteid,
            ] : [
                'slug' => $product->siteid,
                'name' => Str::headline(str_replace('-', ' ', $product->siteid)),
                'site' => $product->siteid,
            ],
            'shop_url' => url("/commerce/{$product->siteid}/shop/search?f_search={$search}"),
        ];
    }

    private function mediaUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return asset('aimeos/'.ltrim($path, '/'));
    }

    private function statusPayload(bool $tablesReady = true): array
    {
        return [
            'tables_ready' => $tablesReady,
            'marketplace_extension' => class_exists('\\Aimeos\\MShop\\Common\\Manager\\Decorator\\Marketplace'),
            'parent_site' => config('shop.mshop.locale.site', 'voca'),
            'native_parent_shop_url' => url('/commerce/'.config('shop.mshop.locale.site', 'voca').'/shop/search'),
        ];
    }
}
