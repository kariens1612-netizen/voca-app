<?php

namespace Database\Seeders;

use App\Models\Ministry;
use App\Models\Organisation;
use Illuminate\Database\Seeder;

class MinistrySeeder extends Seeder
{
    /**
     * Seed the application's ministries.
     */
    public function run(): void
    {
        $organisation = Organisation::firstOrCreate(
            ['slug' => config('tenancy.default_slug', 'voca')],
            ['name' => 'Voca Ministries', 'status' => 'active', 'modules' => Organisation::DEFAULT_MODULES]
        );
        $entities = config('ministries', []);

        foreach ($entities as $entity => $ministries) {
            foreach ($ministries as $ministry) {
                Ministry::updateOrCreate(
                    [
                        'organisation_id' => $organisation->id,
                        'entity' => $entity,
                        'slug' => $ministry['slug'],
                    ],
                    [
                        'name' => $ministry['name'],
                        'description' => $ministry['description'] ?? null,
                        'whatsapp_number' => $ministry['whatsapp_number'] ?? null,
                        'details' => $ministry['details'] ?? null,
                        'theme' => $ministry['theme'] ?? null,
                    ]
                );
            }
        }
    }
}
