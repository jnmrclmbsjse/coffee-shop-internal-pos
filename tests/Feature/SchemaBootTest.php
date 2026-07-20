<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaBootTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_schema_migrates_into_the_postgres_test_database(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        // Tables loaded from the raw-SQL migration.
        $this->assertTrue(Schema::hasTable('business_day'));
        $this->assertTrue(Schema::hasTable('sales_order_item'));

        // Domain enum types were created.
        $types = DB::table('pg_type')->where('typname', 'order_status')->count();
        $this->assertSame(1, $types);

        // Views and the close function exist.
        $this->assertSame(1, DB::table('pg_views')->where('viewname', 'v_cup_balance')->count());
    }
}
