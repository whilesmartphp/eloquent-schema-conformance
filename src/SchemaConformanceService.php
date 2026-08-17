<?php

namespace Whilesmart\SchemaConformance;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compares the live database schema against the declarative spec in
 * config/schema.php. Used by schema:verify (read-only) and schema:conform
 * (additive apply). Additive means it adds missing columns and indexes; it
 * never drops anything or alters an existing column's type.
 */
class SchemaConformanceService
{
    /**
     * Problems found, empty when conformant. Each is
     * ['table' => string, 'kind' => string, 'detail' => string].
     */
    public function verify(): array
    {
        $problems = [];

        foreach (config('schema.tables', []) as $table => $tableSpec) {
            if (! Schema::hasTable($table)) {
                $problems[] = ['table' => $table, 'kind' => 'missing_table', 'detail' => "table `{$table}` does not exist"];

                continue;
            }

            foreach (array_keys($tableSpec['columns'] ?? []) as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $problems[] = ['table' => $table, 'kind' => 'missing_column', 'detail' => "column `{$table}.{$column}` is missing"];
                }
            }

            foreach ($tableSpec['indexes'] ?? [] as $indexSpec) {
                $name = $indexSpec['name'] ?? $this->defaultIndexName($table, $indexSpec['columns'] ?? [], $indexSpec['unique'] ?? false);
                if (! $this->indexExists($table, $name)) {
                    $problems[] = ['table' => $table, 'kind' => 'missing_index', 'detail' => "index `{$name}` on `{$table}` is missing"];
                }
            }
        }

        return $problems;
    }

    /**
     * Apply missing columns and indexes and return the changes made. Does not
     * create missing tables (migrations own that), nor alter or drop anything.
     */
    public function conform(): array
    {
        $applied = [];
        foreach (config('schema.tables', []) as $table => $tableSpec) {
            array_push($applied, ...$this->conformTable($table, $tableSpec));
        }

        return $applied;
    }

    protected function conformTable(string $table, array $tableSpec): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $missingColumns = $this->diffMissingColumns($table, $tableSpec['columns'] ?? []);
        $missingIndexes = $this->diffMissingIndexes($table, $tableSpec['indexes'] ?? []);

        if (empty($missingColumns) && empty($missingIndexes)) {
            return [];
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missingColumns, $missingIndexes) {
            foreach ($missingColumns as $column => $def) {
                $this->addColumn($blueprint, $column, $def);
            }
            foreach ($missingIndexes as $indexSpec) {
                $this->addIndex($blueprint, $indexSpec);
            }
        });

        return $this->buildAppliedChangelog($table, $missingColumns, $missingIndexes);
    }

    protected function diffMissingColumns(string $table, array $columnSpec): array
    {
        $missing = [];
        foreach ($columnSpec as $column => $def) {
            if (! Schema::hasColumn($table, $column)) {
                $missing[$column] = $def;
            }
        }

        return $missing;
    }

    protected function diffMissingIndexes(string $table, array $indexSpecs): array
    {
        $missing = [];
        foreach ($indexSpecs as $indexSpec) {
            $name = $indexSpec['name'] ?? $this->defaultIndexName($table, $indexSpec['columns'] ?? [], $indexSpec['unique'] ?? false);
            if (! $this->indexExists($table, $name)) {
                $missing[] = array_merge($indexSpec, ['name' => $name]);
            }
        }

        return $missing;
    }

    protected function addIndex(Blueprint $blueprint, array $indexSpec): void
    {
        if (! empty($indexSpec['unique'])) {
            $blueprint->unique($indexSpec['columns'], $indexSpec['name']);

            return;
        }
        $blueprint->index($indexSpec['columns'], $indexSpec['name']);
    }

    protected function buildAppliedChangelog(string $table, array $columns, array $indexes): array
    {
        $applied = [];
        foreach (array_keys($columns) as $column) {
            $applied[] = ['table' => $table, 'kind' => 'added_column', 'detail' => "{$table}.{$column}"];
        }
        foreach ($indexes as $indexSpec) {
            $applied[] = ['table' => $table, 'kind' => 'added_index', 'detail' => "{$indexSpec['name']}"];
        }

        return $applied;
    }

    protected function addColumn(Blueprint $blueprint, string $column, array $def): void
    {
        $type = $def['type'] ?? 'string';
        $length = $def['length'] ?? null;

        $col = match ($type) {
            'string' => $blueprint->string($column, $length ?? 255),
            'text' => $blueprint->text($column),
            'longText' => $blueprint->longText($column),
            'tinyInteger' => $blueprint->tinyInteger($column),
            'smallInteger' => $blueprint->smallInteger($column),
            'integer' => $blueprint->integer($column),
            'bigInteger' => $blueprint->bigInteger($column),
            'unsignedInteger' => $blueprint->unsignedInteger($column),
            'unsignedBigInteger' => $blueprint->unsignedBigInteger($column),
            'decimal' => $blueprint->decimal($column, $def['total'] ?? 18, $def['places'] ?? 4),
            'boolean' => $blueprint->boolean($column),
            'date' => $blueprint->date($column),
            'dateTime', 'datetime' => $blueprint->dateTime($column),
            'timestamp' => $blueprint->timestamp($column),
            'json' => $blueprint->json($column),
            'enum' => $blueprint->enum($column, $def['values'] ?? []),
            default => $blueprint->string($column),
        };

        if (! empty($def['nullable'])) {
            $col->nullable();
        }
        if (array_key_exists('default', $def)) {
            $col->default($def['default']);
        }
        if (! empty($def['unique'])) {
            $col->unique();
        }
    }

    /**
     * Driver-agnostic index lookup via the schema builder, so the check works
     * the same on sqlite, MySQL, and Postgres.
     */
    protected function indexExists(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    protected function defaultIndexName(string $table, array $columns, bool $unique): string
    {
        $suffix = $unique ? 'unique' : 'index';

        return $table.'_'.implode('_', $columns).'_'.$suffix;
    }
}
