<?php

namespace App\Console\Commands;

use App\Services\MemberContentSync;
use Illuminate\Console\Command;

class SyncMemberContent extends Command
{
    protected $signature = 'voca:sync-members';
    protected $description = 'Sync approved public website member content into the app database';

    public function handle(MemberContentSync $sync): int
    {
        $count = $sync->run();
        $this->info("Synced {$count} member pages into the app database.");
        return self::SUCCESS;
    }
}
