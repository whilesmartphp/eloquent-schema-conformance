## [1.0.0] - 2026-08-17
- Declarative schema spec in config/schema.php (tables → columns and indexes)
- schema:verify command reporting drift between the spec and the live database
- schema:conform command applying missing columns and indexes additively (never drops or alters)
- EnforceSchemaConformance middleware returning 503 on drift, cached and env-gated
- Auto-conform after every migrate/migrate:fresh via the MigrationsEnded event
- SchemaConformanceAssertions test trait to keep the spec, migrations and models in agreement
- Driver-agnostic index detection so it works under sqlite, MySQL and Postgres
