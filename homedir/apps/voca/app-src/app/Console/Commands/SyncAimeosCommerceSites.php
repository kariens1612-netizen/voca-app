<?php

namespace App\Console\Commands;

use App\Models\Ministry;
use App\Models\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncAimeosCommerceSites extends Command
{
    protected $signature = 'voca:commerce-sync-sites
        {--demo : Load Aimeos demo data into the parent marketplace site}
        {--skip-ministries : Only create or update the parent marketplace site}';

    protected $description = 'Create or update the Voca Aimeos parent site and ministry vendor sites.';

    public function handle(): int
    {
        $parentSite = config('shop.mshop.locale.site', 'voca');
        $demo = $this->option('demo') ? '1' : '0';

        $this->info("Syncing Voca commerce parent site [{$parentSite}]");
        $this->call('aimeos:setup', [
            'site' => $parentSite,
            'tplsite' => 'default',
            '--option' => ["setup/default/demo:{$demo}"],
        ]);

        if ($this->option('skip-ministries')) {
            return self::SUCCESS;
        }

        $ministries = Ministry::query()
            ->with('organisation:id,slug,name')
            ->orderBy('organisation_id')
            ->orderBy('name')
            ->get();

        foreach ($ministries as $ministry) {
            $site = self::siteCode($ministry);
            $this->info("Syncing ministry commerce site [{$site}] for {$ministry->name}");

            $this->call('aimeos:setup', [
                'site' => $site,
                'tplsite' => 'default',
                '--option' => ['setup/default/demo:0'],
            ]);
        }

        $this->newLine();
        $this->info('Voca commerce sites are ready.');
        $this->table(['Voca area', 'Aimeos site', 'Shop URL'], $this->rows($ministries, $parentSite));

        return self::SUCCESS;
    }

    public static function siteCode(Ministry $ministry): string
    {
        $organisation = $ministry->organisation ?: Organisation::query()->find($ministry->organisation_id);

        return Str::slug(($organisation?->slug ?: $ministry->entity).'-'.$ministry->slug);
    }

    private function rows($ministries, string $parentSite): array
    {
        return collect([['Voca marketplace', $parentSite, url("/commerce/{$parentSite}/shop/search")]])
            ->merge($ministries->map(fn (Ministry $ministry) => [
                $ministry->name,
                self::siteCode($ministry),
                url('/commerce/'.self::siteCode($ministry).'/shop/search'),
            ]))
            ->all();
    }
}
