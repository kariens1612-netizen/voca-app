<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status', 20)->default('active')->index();
            $table->string('plan', 40)->default('standard');
            $table->unsignedBigInteger('storage_limit_bytes')->default(1073741824);
            $table->json('branding')->nullable();
            $table->json('settings')->nullable();
            $table->json('modules')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('organisation_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('host')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_token', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('organisation_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organisation_id', 'user_id']);
        });

        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 12)->default('en');
            $table->string('timezone', 64)->default('Africa/Johannesburg');
            $table->json('notifications')->nullable();
            $table->json('dashboard')->nullable();
            $table->json('ministry_subscriptions')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'user_id']);
        });

        Schema::create('tenant_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['organisation_id', 'created_at']);
        });

        $organisationId = DB::table('organisations')->insertGetId([
            'name' => 'Voca Ministries', 'slug' => 'voca', 'status' => 'active',
            'plan' => 'standard', 'storage_limit_bytes' => 1073741824,
            'branding' => json_encode([
                'name' => 'Voca Ministries', 'primary_colour' => '#bd1622',
                'secondary_colour' => '#111827', 'font_family' => 'Inter',
            ]),
            'settings' => json_encode(['locale' => 'en', 'timezone' => 'Africa/Johannesburg']),
            'modules' => json_encode(array_fill_keys([
                'livestreaming', 'events', 'inspiration', 'schedules', 'announcements',
                'kanban', 'bookings', 'public_ministries',
            ], true)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            'ministries', 'ministry_memberships', 'schedules', 'schedule_audits', 'kanban_cards',
            'kanban_comments', 'announcements', 'announcement_ministry', 'live_stream_settings',
            'bookings', 'inspiration_contents', 'inspiration_media', 'event_registrations',
            'schedule_assignments',
        ] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'organisation_id')) continue;
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->foreignId('organisation_id')->nullable()->index()->constrained()->cascadeOnDelete();
            });
            DB::table($table)->whereNull('organisation_id')->update(['organisation_id' => $organisationId]);
        }

        $superadmins = DB::table('users')->where('is_superadmin', true)->pluck('id');
        foreach ($superadmins as $userId) {
            DB::table('organisation_memberships')->insertOrIgnore([
                'organisation_id' => $organisationId, 'user_id' => $userId,
                'role' => 'owner', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ([
            'schedule_assignments', 'event_registrations', 'inspiration_media', 'inspiration_contents',
            'bookings', 'live_stream_settings', 'announcement_ministry', 'announcements',
            'kanban_comments', 'kanban_cards', 'schedule_audits', 'schedules',
            'ministry_memberships', 'ministries',
        ] as $table) {
            if (Schema::hasColumn($table, 'organisation_id')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId('organisation_id'));
            }
        }
        Schema::dropIfExists('tenant_audits');
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('organisation_memberships');
        Schema::dropIfExists('organisation_domains');
        Schema::dropIfExists('organisations');
    }
};
