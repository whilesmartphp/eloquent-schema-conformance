<?php

namespace Whilesmart\SchemaConformance\Testing;

use Illuminate\Support\Facades\Schema;
use Whilesmart\SchemaConformance\SchemaConformanceService;

/**
 * Assertions a host mixes into a PHPUnit test to keep its declared spec, its
 * migrations, and its models in agreement. Parameterized so the package makes
 * no assumption about where a host keeps migrations or models.
 */
trait SchemaConformanceAssertions
{
    /** Blueprint methods that define a column. */
    private function columnMethods(): array
    {
        return [
            'bigInteger', 'boolean', 'char', 'date', 'dateTime', 'decimal', 'double',
            'enum', 'float', 'foreignId', 'integer', 'json', 'jsonb', 'longText',
            'mediumText', 'smallInteger', 'string', 'text', 'time', 'timestamp',
            'tinyInteger', 'unsignedBigInteger', 'unsignedInteger', 'unsignedSmallInteger',
            'unsignedTinyInteger', 'uuid', 'year',
        ];
    }

    /** The declared spec matches the live database. */
    protected function assertSchemaConformant(): void
    {
        $problems = app(SchemaConformanceService::class)->verify();

        $this->assertSame(
            [],
            $problems,
            'config/schema.php declares structures the database does not have: '
                .implode(', ', array_column($problems, 'detail'))
        );
    }

    /**
     * Every column added by an ALTER migration is declared in the spec, so
     * verify can detect it going missing in an environment.
     */
    protected function assertAlteredColumnsDeclared(string $migrationsPath): void
    {
        $declared = collect(config('schema.tables', []))
            ->map(fn (array $spec) => array_keys($spec['columns'] ?? []));

        $undeclared = [];
        foreach ($this->alteredColumnsByTable($migrationsPath) as $table => $columns) {
            foreach ($columns as $column) {
                if (! in_array($column, $declared->get($table, []), true)) {
                    $undeclared[] = "{$table}.{$column}";
                }
            }
        }

        $this->assertSame(
            [],
            $undeclared,
            'These columns are added by an ALTER migration but are not declared in '
                .'config/schema.php, so schema:verify cannot detect them going missing: '
                .implode(', ', $undeclared)
        );
    }

    /**
     * Every fillable/set attribute on the given models maps to a real column.
     *
     * @param  iterable<class-string|object>  $models
     */
    protected function assertModelAttributesHaveColumns(iterable $models): void
    {
        $missing = [];
        foreach ($models as $model) {
            $model = is_string($model) ? new $model : $model;
            $table = $model->getTable();

            if (! Schema::hasTable($table)) {
                $missing[] = $table.' (table missing for '.$model::class.')';

                continue;
            }

            $attributes = array_unique(array_merge($model->getFillable(), array_keys($model->getAttributes())));
            foreach ($attributes as $attribute) {
                if (! Schema::hasColumn($table, $attribute)) {
                    $missing[] = "{$table}.{$attribute} (".$model::class.')';
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            'These model attributes have no matching column: '.implode(', ', $missing)
        );
    }

    /**
     * Columns added by `Schema::table(...)` blocks in the given migrations
     * directory, keyed by table. Migrations in a shape this does not recognise
     * are skipped, so the check never fails on an unusual but correct one.
     *
     * @return array<string, array<int, string>>
     */
    private function alteredColumnsByTable(string $migrationsPath): array
    {
        $byTable = [];

        foreach (glob(rtrim($migrationsPath, '/').'/*.php') as $path) {
            $contents = (string) file_get_contents($path);

            $blocks = preg_split("/Schema::table\(\s*'/", $contents);
            array_shift($blocks);

            foreach ($blocks as $block) {
                if (! preg_match("/^(\w+)'/", $block, $tableMatch)) {
                    continue;
                }

                $methods = implode('|', $this->columnMethods());
                preg_match_all("/\\\$table->({$methods})\(\s*'(\w+)'/", $block, $columnMatches);

                foreach ($columnMatches[2] as $column) {
                    $byTable[$tableMatch[1]][] = $column;
                }
            }
        }

        return array_map('array_unique', $byTable);
    }
}
