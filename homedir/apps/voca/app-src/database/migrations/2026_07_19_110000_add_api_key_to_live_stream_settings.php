<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_stream_settings', function (Blueprint $table) {
            $table->text('api_key')->nullable()->after('notes');
        });

        DB::table('live_stream_settings')
            ->where('platform', 'Rumble')
            ->where(function ($query) {
                $query->whereNull('watch_url')
                    ->orWhere('watch_url', '')
                    ->orWhere('watch_url', 'https://rumble.com/c/VocaMinistriesInt');
            })
            ->update([
                'watch_url' => 'https://rumble.com/v7cy4ji-kingdom-eyes-kingdom-victory.html',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('live_stream_settings', function (Blueprint $table) {
            $table->dropColumn('api_key');
        });
    }
};
