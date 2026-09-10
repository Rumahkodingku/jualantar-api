---
paths:
  - 'app/Modules/*/Tests/**'
---

# Tests

## Module tests live inside the module
Module feature/unit tests live in app/Modules/{Module}/Tests/{Feature,Unit}; cross-cutting/Shared tests stay in tests/. tests/Pest.php and phpunit.xml include the app/Modules/*/Tests globs. Module Feature tests run with RefreshDatabase.
