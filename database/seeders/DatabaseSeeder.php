<?php

namespace Database\Seeders;

use App\Services\CatalogCacheService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CatalogSeeder::class,
            OrderSeeder::class,
        ]);

        // Flush Redis catalog cache so newly seeded catalog data is freshly cached
        app(CatalogCacheService::class)->flushAll();
    }
}
