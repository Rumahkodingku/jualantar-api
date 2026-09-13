---
paths:
  - 'app/Modules/**/Http/Requests/*.php'
  - 'app/Modules/**/Http/Requests/**'
---

# Requests

## Use unqualified table names in unique/exists validation rules
Laravel parses `unique:schema.table,column` as `connection.table`, so schema-qualified names like `identity_access.users` throw "Database connection [identity_access] not configured". This project's tables live in per-module Postgres schemas exposed via DB_SCHEMA search_path, so pass the bare table name (e.g. `Rule::unique('users', 'email')`) and let search_path resolve it. Same for `exists`.

## Use unqualified table names in exists/unique rules
Postgres module schemas resolve via the connection `search_path` (DB_SCHEMA). Write `exists:services,id`, never `exists:service.services,id` — Laravel treats the first dot as a connection name and throws "Database connection [service] not configured". Add each new module schema to DB_SCHEMA in .env/.env.example and config/database.php.
