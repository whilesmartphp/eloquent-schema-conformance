<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Whilesmart\SchemaConformance\SchemaConformanceService;

class SchemaConformanceServiceTest extends TestCase
{
    private function service(): SchemaConformanceService
    {
        return app(SchemaConformanceService::class);
    }

    public function test_verify_reports_then_conform_adds_missing_columns_and_indexes(): void
    {
        Schema::create('widgets', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });

        config()->set('schema.tables', [
            'widgets' => [
                'columns' => [
                    'color' => ['type' => 'string', 'nullable' => true],
                    'qty' => ['type' => 'integer', 'default' => 0],
                ],
                'indexes' => [
                    ['columns' => ['color']],
                ],
            ],
        ]);

        $kinds = array_column($this->service()->verify(), 'kind');
        $this->assertContains('missing_column', $kinds);
        $this->assertContains('missing_index', $kinds);

        $applied = $this->service()->conform();
        $this->assertNotEmpty($applied);

        $this->assertSame([], $this->service()->verify());
        $this->assertTrue(Schema::hasColumn('widgets', 'color'));
        $this->assertTrue(Schema::hasColumn('widgets', 'qty'));
    }

    public function test_verify_reports_a_missing_table_and_conform_does_not_create_it(): void
    {
        config()->set('schema.tables', [
            'ghosts' => ['columns' => ['x' => ['type' => 'string']]],
        ]);

        $problems = $this->service()->verify();
        $this->assertSame('missing_table', $problems[0]['kind']);

        $this->service()->conform();
        $this->assertFalse(Schema::hasTable('ghosts'));
    }

    public function test_conform_is_idempotent(): void
    {
        Schema::create('gadgets', function (Blueprint $t) {
            $t->id();
        });
        config()->set('schema.tables', [
            'gadgets' => ['columns' => ['label' => ['type' => 'string', 'nullable' => true]]],
        ]);

        $this->assertNotEmpty($this->service()->conform());
        $this->assertSame([], $this->service()->conform());
    }
}
