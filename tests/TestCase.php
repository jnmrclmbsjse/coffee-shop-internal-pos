<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Drop views when rebuilding the schema. The POS schema defines
     * v_inventory_status / v_daily_cash_summary / v_cup_balance, which must be
     * torn down before their underlying tables when RefreshDatabase wipes.
     */
    protected bool $dropViews = true;

    /**
     * Drop user-defined types when rebuilding the schema. The raw-SQL migration
     * issues CREATE TYPE for the 13 domain enums; without dropping them,
     * `migrate:fresh` fails on the next run with "type already exists".
     */
    protected bool $dropTypes = true;
}
