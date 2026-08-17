<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnforceSchemaConformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('schema.conformance')->get('/_probe', fn () => response()->json(['ok' => true]));
        config()->set('schema.enforce', true);
        config()->set('schema.cache_ttl', 0);
    }

    public function test_it_returns_503_with_problems_on_drift(): void
    {
        config()->set('schema.tables', ['ghosts' => ['columns' => ['x' => ['type' => 'string']]]]);
        Cache::flush();

        $this->getJson('/_probe')
            ->assertStatus(503)
            ->assertJsonPath('problems.0.kind', 'missing_table');
    }

    public function test_it_passes_when_conformant(): void
    {
        config()->set('schema.tables', []);
        Cache::flush();

        $this->getJson('/_probe')->assertOk()->assertJsonPath('ok', true);
    }

    public function test_it_bypasses_when_enforcement_is_off(): void
    {
        config()->set('schema.enforce', false);
        config()->set('schema.tables', ['ghosts' => ['columns' => ['x' => ['type' => 'string']]]]);
        Cache::flush();

        $this->getJson('/_probe')->assertOk();
    }
}
