<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('synced_member_pages', function (Blueprint $table) {
            $table->id();
            $table->string('path', 200)->unique();
            $table->json('content');
            $table->timestamp('synced_at');
        });
    }

    public function down(): void { Schema::dropIfExists('synced_member_pages'); }
};
