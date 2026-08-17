# eloquent-schema-conformance

A declarative database schema-conformance guard for Laravel. Declare the columns and indexes each feature expects, verify the live database against them, additively conform, and optionally refuse to serve while the schema drifts.

It exists to catch a specific class of bug: a migration that **alters** an existing table can reach one environment and not another, leaving a table that looks intact but rejects writes. Instead of editing old migrations defensively, you declare the expected end state once; the guard turns "an ALTER never ran here" into a clear 503 (or a failing test) rather than a runtime write error.

## Install

```bash
composer require whilesmart/eloquent-schema-conformance
php artisan vendor:publish --tag=schema-config
```

## Declare the spec

In `config/schema.php`, each feature declares the table state it expects after its migrations run:

```php
'tables' => [
    'transactions' => [
        'columns' => [
            'intent' => ['type' => 'string', 'default' => 'regular'],
            'metadata' => ['type' => 'json', 'nullable' => true],
        ],
        'indexes' => [
            ['columns' => ['intent']],
        ],
    ],
],
```

Column specs use Blueprint types (`string`, `integer`, `json`, `enum` with `values`, …) with `nullable`, `default`, `length`, `unique` modifiers. Index specs are `['columns' => [...], 'name' => 'optional', 'unique' => bool]`.

## Commands

| Command | Purpose |
| --- | --- |
| `schema:verify` | Read-only. Reports every missing table, column, or index. |
| `schema:conform` | Adds missing columns and indexes in place. Never drops or alters. `--dry-run` to preview. |

`schema:conform` also runs automatically after every `migrate`/`migrate:fresh` (disable with `SCHEMA_CONFORMANCE_AUTO_CONFORM=false`).

## Enforce at the edge

Attach the middleware (aliased `schema.conformance`) to a route group. While the live schema drifts from the spec, requests get a 503 listing the problems; the result is cached for `schema.cache_ttl` seconds. Disable with `SCHEMA_CONFORMANCE_ENFORCE=false` in local dev. Paths in `schema.bypass_paths` are never gated.

## Keep the spec honest in tests

```php
use Whilesmart\SchemaConformance\Testing\SchemaConformanceAssertions;

class SchemaConformanceTest extends TestCase
{
    use SchemaConformanceAssertions;

    public function test_schema(): void
    {
        $this->assertSchemaConformant();
        $this->assertAlteredColumnsDeclared(database_path('migrations'));
        $this->assertModelAttributesHaveColumns(/* your models */);
    }
}
```

## Testing

```bash
composer test
composer pint:test
```
