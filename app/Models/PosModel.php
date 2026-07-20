<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared base for every POS domain model. Encodes the conventions that the
 * Postgres schema forces on all of them (see docs/schema/schema.sql):
 *
 * - UUID primary keys. IDs are generated PHP-side via HasUuids so they exist
 *   before save; the column's gen_random_uuid() default is a fallback only.
 * - Non-incrementing string keys.
 * - Tables are singular snake_case, so each subclass MUST set `$table`
 *   (Eloquent would otherwise pluralize).
 * - Every table has `created_at`; only some have `updated_at`. This base tracks
 *   `created_at` only — subclasses whose table has an `updated_at` column should
 *   re-enable it with `const UPDATED_AT = 'updated_at';`.
 *
 * Money/total columns are DB-generated or trigger-maintained; keep them out of
 * `#[Fillable]` and never recompute them in PHP (see CLAUDE.md / EXISTING-PATTERNS §6).
 */
abstract class PosModel extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Track created_at only by default; tables without updated_at are the norm.
     * Subclasses with an updated_at column override this constant.
     */
    const UPDATED_AT = null;
}
