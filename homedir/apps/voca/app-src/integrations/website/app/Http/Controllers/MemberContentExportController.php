<?php

namespace App\Http\Controllers;

use App\Services\PublicMemberContent;
use Illuminate\Http\Request;

class MemberContentExportController extends Controller
{
    public function __invoke(Request $request, PublicMemberContent $parser)
    {
        $key = config('services.mobile_members.key');
        abort_unless(is_string($key) && strlen($key) >= 32 && hash_equals($key, (string) $request->header('X-Voca-Member-Key')), 403);
        $base = rtrim(config('app.url'), '/');
        $controller = app(AffiliateController::class);
        $views = [
            '/members' => $controller->index(),
            '/members/churches-ministries' => $controller->churchesMinistries(),
            '/members/businesses' => $controller->businesses(),
            '/members/pastors' => $controller->pastors(),
            '/members/individuals' => $controller->individuals(),
            '/members/partners' => $controller->partners(),
        ];
        $profiles = app(AffiliateProfileController::class);
        foreach ($profiles->apiIndex()->getData(true)['data'] as $profile) {
            $views['/affiliates/'.$profile['slug']] = $profiles->show($profile['slug']);
        }
        $pages = [];
        foreach ($views as $path => $view) {
            // Reuse the website's approval rules and public view data. Never
            // export authentication details or private registration fields.
            $content = $parser->parse($view->render(), $base);
            abort_unless(count($content['sections']), 503, 'Member export is incomplete.');
            $pages[] = compact('path', 'content');
        }
        return response()->json(['version' => 1, 'pages' => $pages])->header('Cache-Control', 'no-store');
    }
}
