---
paths:
  - 'app/Modules/*/Database/Migrations/**'
---

# Migrations

## PostgreSQL schema per module
Each module owns a Postgres schema (identity_access, geography, bank_directory) and must schema-qualify its tables, e.g. Schema::create('geography.provinces', ...). Migrations must call the App\Shared\Database\Concerns\CreatesSchema trait's ensureSchema() first. Foreign keys are allowed within a module but never across module schemas; store cross-module IDs as plain columns. config/database.php pgsql search_path includes all module schemas so db:wipe/migrate:fresh covers them.
