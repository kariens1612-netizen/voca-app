<?php

namespace App\Console\Commands;

use App\Models\MovementRoster;
use App\Models\Organisation;
use App\Services\DelegateImport;
use App\Tenancy\CurrentOrganisation;
use Illuminate\Console\Command;

class ImportMovementDelegates extends Command
{
    protected $signature = 'delegates:import {file} {--organisation=voca} {--roster=VOCA Delegates}';
    protected $description = 'Import a local XLSX or CSV into a tenant delegate movement roster';

    public function handle(DelegateImport $importer): int
    {
        $organisation = Organisation::where('slug', $this->option('organisation'))->firstOrFail();
        app(CurrentOrganisation::class)->set($organisation);
        try {
            $path = $this->argument('file');
            if (! is_file($path)) { $this->error('File not found.'); return self::FAILURE; }
            $rows = $importer->read($path, strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xlsx');
            $roster = MovementRoster::firstOrCreate(['name' => $this->option('roster')]);
            $count = $importer->import($roster, $rows);
            $this->info("Imported {$count} delegates into {$roster->name} (roster {$roster->id}).");
            return self::SUCCESS;
        } finally { app(CurrentOrganisation::class)->set(null); }
    }
}
