<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaCommandsTest extends TestCase
{
    public function test_verify_fails_on_drift_and_succeeds_when_conformant(): void
    {
        config()->set('schema.tables', ['ghosts' => ['columns' => ['x' => ['type' => 'string']]]]);
        $this->artisan('schema:verify')->assertExitCode(1);

        config()->set('schema.tables', []);
        $this->artisan('schema:verify')->assertExitCode(0);
    }

    public function test_conform_applies_additive_changes(): void
    {
        Schema::create('items', function (Blueprint $t) {
            $t->id();
        });
        config()->set('schema.tables', ['items' => ['columns' => ['sku' => ['type' => 'string', 'nullable' => true]]]]);

        $this->artisan('schema:conform')->assertExitCode(0);

        $this->assertTrue(Schema::hasColumn('items', 'sku'));
    }

    public function test_conform_dry_run_changes_nothing(): void
    {
        Schema::create('parts', function (Blueprint $t) {
            $t->id();
        });
        config()->set('schema.tables', ['parts' => ['columns' => ['code' => ['type' => 'string', 'nullable' => true]]]]);

        $this->artisan('schema:conform', ['--dry-run' => true])->assertExitCode(0);

        $this->assertFalse(Schema::hasColumn('parts', 'code'));
    }
}
