<?php

/**
 * Schema conformance specifications.
 *
 * Each feature declares the table state it expects after all its migrations
 * have run. `schema:verify` compares this against the live DB; `schema:conform`
 * applies missing additive changes in place. The app can refuse to serve when
 * non-conformant via the EnforceSchemaConformance middleware.
 *
 * Column specs use Laravel Blueprint method names. Modifiers are expressed as a
 * keyed array: ['type' => 'string', 'nullable' => true, 'length' => 255].
 *
 * Supported column `type` values: string, text, longText, tinyInteger,
 * smallInteger, integer, bigInteger, unsignedInteger, unsignedBigInteger,
 * decimal, boolean, date, dateTime, timestamp, json, enum (with `values`).
 *
 * Index specs: ['columns' => [...], 'name' => 'optional', 'unique' => bool].
 *
 * Any migration that ALTERS an existing table should also declare what it adds
 * here. A create migration either ran or the table is absent (which verify
 * reports on its own), but an ALTER that never reached an environment leaves a
 * table that looks fine and fails on write.
 */

return [
    // When true, EnforceSchemaConformance returns 503 while the live schema
    // drifts from the spec below. Disable in local dev to experiment.
    'enforce' => env('SCHEMA_CONFORMANCE_ENFORCE', true),

    // Run schema:conform automatically after every migrate/migrate:fresh.
    'auto_conform' => env('SCHEMA_CONFORMANCE_AUTO_CONFORM', true),

    // How long (seconds) the middleware caches a conformance result.
    'cache_ttl' => (int) env('SCHEMA_CONFORMANCE_CACHE_TTL', 60),

    // Request paths the middleware never gates, so a fix stays reachable while
    // the app is refusing to serve.
    'bypass_paths' => ['schema/*'],

    // feature => ['columns' => [...], 'indexes' => [...]]
    'tables' => [],
];
