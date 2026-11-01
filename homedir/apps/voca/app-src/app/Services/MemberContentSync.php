<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class MemberContentSync
{
    public function run(): int
    {
        $base = rtrim((string) config('services.voca_members.url'), '/');
        $key = config('services.voca_members.key');
        if (!str_starts_with($base, 'https://') || !is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('Configure the HTTPS website URL and shared VOCA_MEMBER_API_KEY before syncing.');
        }
        $response = Http::acceptJson()->withHeaders(['X-Voca-Member-Key' => $key])
            ->connectTimeout(5)->timeout(60)->withOptions(['allow_redirects' => false])
            ->get($base.'/api/mobile-members/content');
        $response->throw();
        $data = $response->json();
        Validator::make(is_array($data) ? $data : [], [
            'version' => ['required', 'integer', 'in:1'],
            'pages' => ['required', 'array', 'min:6', 'max:10000'],
            'pages.*.path' => ['required', 'string', 'max:200', 'distinct', 'regex:~^/(members(?:/(churches-ministries|businesses|pastors|individuals|partners))?|affiliates/[a-z0-9][a-z0-9-]{0,100})$~'],
            'pages.*.content.sections' => ['required', 'array', 'min:1'],
            'pages.*.content.sections.*.title' => ['present', 'string'],
            'pages.*.content.sections.*.paragraphs' => ['present', 'array'],
            'pages.*.content.sections.*.images' => ['present', 'array'],
            'pages.*.content.sections.*.links' => ['present', 'array'],
            'pages.*.content.sections.*.cards' => ['present', 'array'],
        ])->validate();
        $paths = array_column($data['pages'], 'path');
        foreach (['/members', '/members/churches-ministries', '/members/businesses', '/members/pastors', '/members/individuals', '/members/partners'] as $required) {
            if (!in_array($required, $paths, true)) throw new RuntimeException('Incomplete member snapshot; keeping the previous copy.');
        }
        // Replace the complete public snapshot atomically, including unpublishing.
        // Failed requests or validation never remove the last successful copy.
        return DB::transaction(function () use ($data, $paths) {
            foreach ($data['pages'] as $page) {
                DB::table('synced_member_pages')->updateOrInsert(['path' => $page['path']], [
                    'content' => json_encode($page['content'], JSON_THROW_ON_ERROR),
                    'synced_at' => now(),
                ]);
            }
            DB::table('synced_member_pages')->whereNotIn('path', $paths)->delete();
            return count($paths);
        });
    }
}
