<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Loads the worked demo business day from docs/schema/seed.sql
 * (catalog, staff, one full day exercising both reconciliations).
 * Run against a fresh DB: `php artisan db:seed --class=DemoDaySeeder`.
 */
class DemoDaySeeder extends Seeder
{
    public function run(): void
    {
        DB::unprepared(file_get_contents(base_path('docs/schema/seed.sql')));
    }
}
