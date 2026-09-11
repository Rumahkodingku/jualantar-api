---
paths:
  - 'app/Modules/**/Http/Requests/*.php'
---

# Requests

## Use unqualified table names in unique/exists validation rules
Laravel parses `unique:schema.table,column` as `connection.table`, so schema-qualified names like `identity_access.users` throw "Database connection [identity_access] not configured". This project's tables live in per-module Postgres schemas exposed via DB_SCHEMA search_path, so pass the bare table name (e.g. `Rule::unique('users', 'email')`) and let search_path resolve it. Same for `exists`.
