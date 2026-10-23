<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('live_stream_settings')
            ->whereRaw('LOWER(platform) = ?', ['odysee'])
            ->update([
                'platform' => 'Rumble',
                'embed_url' => '',
                'watch_url' => 'https://rumble.com/c/VocaMinistriesInt',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('live_stream_settings')
            ->whereRaw('LOWER(platform) = ?', ['rumble'])
            ->where('watch_url', 'https://rumble.com/c/VocaMinistriesInt')
            ->update([
                'platform' => 'Odysee',
                'embed_url' => '',
                'watch_url' => '',
                'updated_at' => now(),
            ]);
    }
};
