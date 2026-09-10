---
paths:
  - 'app/Modules/**'
  - 'app/Modules/*/*ServiceProvider.php'
---

# Modules

## Modular Monolith module boundaries
Business code lives in app/Modules/{Module} (Domain, Application, Infrastructure, Http, Database, Routes, Contracts); generic technical code lives in app/Shared. Other modules may only import a module's Contracts/ (interface + DTO). Never add cross-module Eloquent relations or query another module's Domain/Models directly — go through a Contract bound in that module's ServiceProvider. Shared must never depend on App\Modules. Enforced by tests/Arch/ModuleBoundaryTest.php.

## Explicit module registration
Every module has {Module}ServiceProvider registered manually in bootstrap/providers.php (no auto-discovery), a module.json manifest, and loads its own migrations and versioned routes (Route::middleware('api')->prefix('api/v1')->name('api.v1.')) from within the provider. Models use #[Table('schema.table')] and #[UseFactory(ModuleFactory::class)]; do not use the deprecated loadFactoriesFrom.
