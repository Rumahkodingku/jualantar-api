---
paths:
  - 'app/Modules/**/Http/Controllers/**'
---

# Controllers

## Document fromResult endpoints for Scramble
Scramble auto-infers the `{ data: ... }` envelope for endpoints returning `ApiResponse::success/paginated/created/noContent` directly, but NOT for `ApiResponse::fromResult` (the success closure is unanalyzable). For those, add `#[Response(status, type: 'array{data: Resource}')]` and `#[IgnoreResponse(200)]` when the real status is 201/204. Error responses are documented centrally by `App\Shared\OpenApi\ProblemDetailsOperationTransformer` (RFC 9457); add new conflict routes there and new codes in `config/api.php`. Docs (Scalar) live at `/docs/api` + `/docs/api.json`, gated to non-production in `AppServiceProvider`.
