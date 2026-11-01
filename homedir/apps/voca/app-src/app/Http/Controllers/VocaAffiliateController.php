<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class VocaAffiliateController extends Controller
{
    public function index(?string $slug = null)
    {
        abort_if($slug && !preg_match('/^[a-z0-9][a-z0-9-]{0,100}$/', $slug), 404);
        return $this->page($slug ? '/affiliates/'.$slug : '/members');
    }

    public function pathway(string $group)
    {
        abort_unless(in_array($group, ['churches-ministries', 'businesses', 'pastors', 'individuals', 'partners']), 404);
        return $this->page('/members/'.$group);
    }

    private function page(string $path)
    {
        $page = DB::table('synced_member_pages')->where('path', $path)->first();
        if (!$page) {
            $ready = DB::table('synced_member_pages')->where('path', '/members')->exists();
            return response()->json(['message' => $ready ? 'Member information not found.' : 'Member information has not synced yet.'], $ready ? 404 : 503);
        }
        return response()->json(json_decode($page->content, true, 512, JSON_THROW_ON_ERROR));
    }
}
