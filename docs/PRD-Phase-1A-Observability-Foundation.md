# PRD — Phase 1A Observability Foundation

## 1. Overview

**Project:** JualAntar RESTful API  
**Phase:** 1A — Observability Foundation  
**Status:** Planned

Phase 1A melengkapi fondasi observability yang sudah tersedia pada codebase API JualAntar sebelum pengembangan fitur API berkembang lebih jauh.

Codebase saat ini sudah memiliki:

- Global API exception handling.
- RFC 9457 Problem Details response.
- `X-Trace-Id` request/context/response propagation.
- Laravel Context untuk menyimpan trace ID.
- Health check `/up`.
- Unit dan feature tests untuk beberapa bagian tersebut.

Phase 1A berfokus pada melengkapi fondasi tersebut dengan **structured logging, HTTP request logging, basic metrics, dan testing observability**.

---

# 2. Objective

Membangun observability foundation yang:

- Memudahkan debugging API.
- Memiliki correlation identifier yang konsisten.
- Menghasilkan log yang terstruktur dan dapat diproses mesin.
- Mencatat request HTTP dan latency.
- Menyediakan metrik dasar API.
- Memiliki test coverage untuk behavior observability.
- Tidak mengunci project pada observability stack tertentu.
- Siap dikembangkan menuju Prometheus, Grafana, Loki, dan OpenTelemetry pada fase berikutnya.

---

# 3. Current State

## Already Implemented

### Request ID / Trace ID

`RequestIdMiddleware` saat ini:

1. Membaca `X-Trace-Id` dari request.
2. Membuat UUID jika header tidak tersedia.
3. Menyimpan ID ke Laravel `Context`.
4. Mengembalikan ID melalui response header.

Flow saat ini:

```text
HTTP Request
     ↓
RequestIdMiddleware
     ↓
X-Trace-Id
     ↓
Laravel Context
     ↓
Application
     ↓
HTTP Response
     ↓
X-Trace-Id
```

### Global Error Handling

API sudah menggunakan `ProblemDetailsFactory` untuk menghasilkan RFC 9457 Problem Details response.

Response error sudah dapat membawa:

```json
{
    "status": 500,
    "code": "internal_server_error",
    "trace_id": "..."
}
```

### Health Check

Laravel health endpoint sudah tersedia:

```text
GET /up
```

### Existing Tests

Sudah tersedia test untuk:

- `RequestIdMiddleware`
- `ProblemDetailsFactory`
- Problem responses
- `ApiResponse`
- Exception handling

Phase 1A harus melanjutkan pola testing tersebut.

---

# 4. Scope

Phase 1A mencakup:

```text
1. Structured Logging
2. HTTP Request Logging
3. Request/Trace ID improvement
4. Exception Logging
5. Basic Metrics Foundation
6. Health Check Verification
7. Observability Tests
```

---

# 5. Structured Logging

## Objective

Mengubah logging API menjadi structured logging yang konsisten dan mudah diproses oleh log aggregation system di masa depan.

Log harus memiliki context yang relevan.

Contoh:

```json
{
    "level": "INFO",
    "message": "HTTP request completed",
    "trace_id": "uuid",
    "method": "POST",
    "path": "/api/v1/orders",
    "status": 201,
    "duration_ms": 142
}
```

## Requirements

- Gunakan Laravel/Monolog logging infrastructure yang sudah tersedia.
- Jangan membuat custom logging system yang tidak diperlukan.
- Support structured context.
- Pastikan `trace_id` tersedia pada log context.
- Jangan mencatat password, token, authorization header, cookie, atau sensitive credentials.
- Jangan mencatat seluruh request body secara default.
- Logging level harus dapat dikontrol melalui environment configuration.
- Development dan production dapat menggunakan konfigurasi level berbeda.

---

# 6. HTTP Request Logging

Buat middleware khusus untuk mencatat lifecycle request HTTP.

Minimal informasi:

```text
timestamp
trace_id
HTTP method
request path
HTTP status
duration
```

Jika relevan, dapat mencatat:

```text
route
user identifier
client IP
```

Namun data sensitif tidak boleh dicatat.

## Request Flow

```text
Request
   ↓
Request ID Middleware
   ↓
Request Logging Middleware
   ↓
Application
   ↓
Response
   ↓
Log completion
```

## Example

```text
POST /api/v1/orders
status=201
duration=142ms
trace_id=...
```

## Error Request

Request yang menghasilkan error tetap harus memiliki log yang dapat dikorelasikan menggunakan `trace_id`.

---

# 7. Request/Trace ID Improvement

Pertahankan mekanisme `X-Trace-Id` yang sudah ada karena sudah digunakan oleh response dan error handling.

Phase 1A harus:

- Memastikan trace ID selalu tersedia pada API request.
- Memastikan trace ID tersedia di Laravel Context.
- Memastikan trace ID dikembalikan melalui response header.
- Memastikan trace ID tersedia pada structured log.
- Memastikan trace ID tersedia pada Problem Details response.

Flow target:

```text
Request
  │
  ├── X-Trace-Id
  │
  ▼
Context
  │
  ├── Structured Log
  ├── Request Log
  └── Exception Log
  │
  ▼
Response
  │
  ├── X-Trace-Id
  └── Problem Details trace_id
```

## Validation

Incoming `X-Trace-Id` harus diperlakukan sebagai untrusted input.

Jika format/length validation diperlukan, implementasikan secara sederhana tanpa mengganti konsep trace ID yang sudah ada.

Jangan mengubahnya menjadi distributed tracing/OpenTelemetry pada phase ini.

---

# 8. Exception Logging

Exception harus dapat ditemukan melalui log dan dikorelasikan dengan request menggunakan `trace_id`.

Untuk unexpected exception, log minimal harus memiliki:

```text
level
message
exception class
trace_id
HTTP method
path
status
```

Stack trace boleh dicatat pada log backend sesuai logging policy.

Response kepada client tetap menggunakan `ProblemDetailsFactory`.

Jangan membocorkan:

- Stack trace
- File path
- Internal implementation details
- Credentials
- Secrets

ke production API response.

---

# 9. Basic Metrics Foundation

Phase 1A tidak perlu langsung memasang Prometheus atau Grafana.

Siapkan foundation agar aplikasi dapat mengumpulkan metrik dasar tanpa mengikat business logic dengan metrics backend tertentu.

Minimal metrics:

```text
HTTP request count
HTTP error count
HTTP request duration
```

Metrics sebaiknya dapat dibedakan berdasarkan:

```text
HTTP method
route/path
status code
```

Hindari high-cardinality labels seperti:

```text
user_id
order_id
trace_id
email
```

## Target Metrics

Contoh konsep:

```text
http_requests_total
http_request_duration
http_errors_total
```

Nama final harus mengikuti convention yang dipilih saat implementasi.

## Important

Phase 1A hanya membutuhkan **metrics foundation/instrumentation**.

Jangan menambahkan:

- Grafana
- Prometheus server
- Loki
- Tempo
- OpenTelemetry collector

ke scope Phase 1A kecuali memang dibutuhkan oleh implementasi foundation dan disetujui kemudian.

---

# 10. Health Check Verification

Pastikan endpoint:

```text
GET /up
```

tetap berfungsi setelah observability middleware ditambahkan.

Health endpoint tidak boleh menghasilkan logging/metrics yang berlebihan jika tidak diperlukan.

Jika health check dipakai untuk monitoring infrastructure, behavior-nya harus tetap lightweight.

---

# 11. Middleware Ordering

Observability middleware harus memiliki ordering yang benar.

Target:

```text
HTTP Request
     ↓
Request ID
     ↓
Request Logging
     ↓
Application Middleware
     ↓
Controller
     ↓
Response
     ↓
Request Log
```

Pastikan:

- `trace_id` tersedia sebelum request logging dilakukan.
- Error tetap dapat dicatat.
- Response tetap mendapatkan `X-Trace-Id`.
- Existing authentication/authorization behavior tidak rusak.

Jangan mengubah middleware yang tidak berkaitan tanpa alasan.

---

# 12. Sensitive Data Policy

Observability implementation **tidak boleh menyebabkan data sensitif masuk ke log**.

Jangan log:

```text
password
password_confirmation
access_token
refresh_token
authorization header
cookie
session secrets
API keys
payment credentials
full sensitive request body
```

Jika user identifier diperlukan untuk debugging, gunakan identifier yang aman dan relevan.

---

# 13. Testing

Tambahkan test khusus untuk observability foundation.

## Request ID

Test:

- Trace ID dibuat jika request tidak memiliki header.
- Incoming trace ID dipertahankan.
- Context memiliki trace ID.
- Response memiliki `X-Trace-Id`.

## Request Logging

Test:

- Request menghasilkan log.
- Log memiliki HTTP method.
- Log memiliki path/route.
- Log memiliki status.
- Log memiliki duration.
- Log memiliki trace ID.

## Exception Logging

Test:

- Unexpected exception menghasilkan log.
- Log memiliki trace ID.
- Error response tetap menggunakan Problem Details.
- Production response tidak membocorkan internal exception details.

## Metrics

Test:

- Request menghasilkan metric/instrumentation.
- Status code dapat dibedakan.
- Error request meningkatkan error metric.
- Duration tercatat.

## Health Check

Test:

```text
GET /up → successful response
```

Pastikan perubahan observability tidak merusak endpoint tersebut.

---

# 14. Configuration

Observability behavior harus dapat dikonfigurasi melalui environment/configuration.

Contoh kebutuhan configuration:

```text
LOG_LEVEL
LOG_CHANNEL
```

Jika diperlukan:

```text
OBSERVABILITY_ENABLED
```

Jangan membuat configuration yang tidak digunakan.

Configuration harus memiliki default yang aman untuk development.

---

# 15. Architecture

Target architecture:

```text
                    ┌── Structured Logs
                    │
HTTP Request ───────┼── Request Metrics
                    │
                    └── Exception Logs
                         │
                         ▼
                    Trace ID Context
```

Application flow:

```text
Request
  ↓
RequestIdMiddleware
  ↓
RequestLoggingMiddleware
  ↓
Application
  ↓
Exception Handling
  ↓
Structured Logging
  ↓
Response
```

Observability code harus tetap terpisah dari business logic.

Contoh:

```text
app/
├── Http/
│   └── Middleware/
│       ├── RequestIdMiddleware.php
│       └── RequestLoggingMiddleware.php
│
└── Support/
    └── Observability/
        └── ...
```

Struktur final boleh mengikuti architecture yang sudah ada jika codebase memiliki convention yang lebih tepat.

---

# 16. Maintainability

Implementasi harus:

- Reusable.
- Testable.
- Tidak tightly coupled dengan controller.
- Tidak tightly coupled dengan business modules.
- Tidak membutuhkan perubahan pada setiap endpoint.
- Menggunakan Laravel/Monolog primitives jika sudah mencukupi.
- Mudah dikembangkan ke Prometheus/OpenTelemetry nanti.

Jangan membuat abstraction besar hanya untuk memenuhi Phase 1A.

---

# 17. Out of Scope

Tidak termasuk dalam Phase 1A:

```text
Prometheus deployment
Grafana deployment
Loki
Tempo
OpenTelemetry
Distributed tracing
Alertmanager
Production monitoring infrastructure
Log aggregation infrastructure
Advanced business metrics
Real-time monitoring dashboard
```

Komponen tersebut dapat menjadi fase observability berikutnya.

---

# 18. Implementation Order

Implementasikan secara bertahap:

```text
1. Audit existing observability implementation
        ↓
2. Finalize logging strategy
        ↓
3. Improve trace ID handling if required
        ↓
4. Implement structured logging
        ↓
5. Implement HTTP request logging
        ↓
6. Implement exception logging
        ↓
7. Implement basic metrics foundation
        ↓
8. Verify /up health check
        ↓
9. Add observability tests
        ↓
10. Run full test suite
        ↓
11. Run static analysis / formatter
        ↓
12. Document implementation
```

---

# 19. Acceptance Criteria

## Logging

- [x] API menggunakan structured logging.
- [x] Log memiliki trace ID.
- [x] HTTP request completion dapat ditemukan melalui log.
- [x] Request duration tercatat.
- [x] HTTP status tercatat.
- [x] Unexpected exception tercatat.
- [x] Sensitive data tidak tercatat.

## Trace ID

- [x] Trace ID dibuat jika tidak tersedia.
- [x] Incoming `X-Trace-Id` dapat digunakan.
- [x] Trace ID tersedia di Context.
- [x] Trace ID tersedia di response header.
- [x] Trace ID tersedia di Problem Details.
- [x] Trace ID tersedia di structured logs.

## Metrics

- [x] Request count tersedia melalui metrics foundation.
- [x] Error count tersedia.
- [x] Request duration tersedia.
- [x] Labels tidak menggunakan high-cardinality identifiers.

## Health

- [x] `GET /up` tetap berfungsi.
- [x] Health check tetap lightweight.

## Testing

- [x] Request ID tests tetap passing.
- [x] Request logging tests tersedia.
- [x] Exception logging tests tersedia.
- [x] Metrics tests tersedia.
- [x] Health check test tersedia.

## Quality

- [x] Tidak ada perubahan business logic yang tidak diperlukan.
- [x] Tidak ada observability code di controller/service tanpa alasan.
- [x] Tidak ada dependency besar yang ditambahkan tanpa kebutuhan.
- [x] Formatter/type checks/static analysis passing.

---

# 20. Definition of Done

Phase 1A dianggap selesai ketika:

```text
Structured Logging
        +
HTTP Request Logging
        +
Trace ID Context
        +
Exception Logging
        +
Basic Metrics Foundation
        +
Health Check
        +
Automated Tests
        ↓
Observability Foundation Ready
```

API kemudian dapat melanjutkan pengembangan feature modules tanpa harus menunggu Grafana/Prometheus/Loki/Tempo selesai.

---

# 21. Future Evolution

Phase 1A harus menjadi fondasi untuk evolusi berikutnya:

```text
Phase 1A
Observability Foundation
        ↓
Phase 1B
Prometheus + Metrics
        ↓
Phase 2
Grafana Dashboard
        ↓
Phase 3
Centralized Logging / Loki
        ↓
Phase 4
OpenTelemetry + Distributed Tracing
        ↓
Production Monitoring & Alerting
```

Tujuan Phase 1A bukan membangun seluruh observability stack, tetapi memastikan setiap request API JualAntar sejak awal **dapat diidentifikasi, dicatat, diukur, dan didiagnosis secara konsisten**.
