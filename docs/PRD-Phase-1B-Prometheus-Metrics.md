# PRD — Phase 1B Prometheus & Metrics

## 1. Overview

**Project:** JualAntar RESTful API  
**Phase:** 1B — Prometheus & Metrics  
**Status:** Planned

Phase 1B melanjutkan **Phase 1A — Observability Foundation** dengan menyediakan metrics collection yang dapat dikonsumsi oleh Prometheus.

Phase 1A menyediakan fondasi:

- Structured logging
- HTTP request logging
- Request/Trace ID
- Exception logging
- Basic metrics instrumentation
- Health check
- Automated tests

Phase 1B berfokus pada **exposing dan collecting application metrics menggunakan Prometheus**, tanpa membangun dashboard Grafana terlebih dahulu.

---

# 2. Objective

Menyediakan metrics JualAntar API yang:

- Dapat dikumpulkan oleh Prometheus.
- Dapat digunakan untuk mengukur traffic, latency, dan error rate.
- Memiliki label yang aman dan terkontrol.
- Tidak menggunakan high-cardinality data.
- Tidak mengganggu business logic.
- Dapat menjadi source untuk Grafana pada Phase 2.
- Cocok untuk development dan production deployment.

---

# 3. Scope

Phase 1B mencakup:

```text
1. Prometheus integration
2. Metrics endpoint
3. HTTP request metrics
4. HTTP error metrics
5. HTTP latency metrics
6. Basic application metrics foundation
7. Prometheus configuration
8. Docker Compose integration
9. Metrics tests
10. Basic documentation
```

---

# 4. Prerequisite

Phase 1A harus sudah selesai atau berada dalam kondisi stabil.

Minimal tersedia:

```text
Request ID
Structured Logging
HTTP Request Logging
Exception Logging
Basic Metrics Instrumentation
Health Check
Automated Tests
```

Phase 1B tidak boleh menghapus atau merusak behavior observability Phase 1A.

---

# 5. Prometheus Architecture

Target architecture:

```text
JualAntar API
      │
      │ /metrics
      ▼
Prometheus
      │
      ▼
Future Grafana
```

Prometheus melakukan scraping metrics dari API secara periodik.

Untuk local development:

```text
Docker Compose
├── JualAntar API
└── Prometheus
```

Grafana belum termasuk dalam Phase 1B.

---

# 6. Metrics Endpoint

Expose endpoint khusus untuk Prometheus.

Recommended:

```text
GET /metrics
```

Endpoint harus menghasilkan metrics dalam Prometheus-compatible exposition format.

Contoh:

```text
http_requests_total{method="GET",route="/api/v1/users",status="200"} 42
```

Metrics endpoint harus:

- Tidak membutuhkan authentication jika Prometheus internal network memerlukannya.
- Tidak mengekspos application secrets.
- Tidak mengekspos user data.
- Tidak mengekspos request/response body.
- Tidak mengekspos internal configuration.
- Dibatasi pada metrics yang memang diperlukan.

Jika project architecture membutuhkan protected metrics endpoint, gunakan pendekatan internal-network/authentication yang sesuai tanpa mengekspos endpoint secara publik.

---

# 7. HTTP Request Metrics

Minimal metrics:

```text
http_requests_total
http_request_duration_seconds
http_errors_total
```

Metric naming dapat disesuaikan dengan library/convention yang digunakan selama tetap konsisten dengan Prometheus conventions.

## Request Count

Hitung setiap HTTP request yang selesai.

Dimensi yang diperbolehkan:

```text
method
route
status
```

Contoh:

```text
http_requests_total{
  method="POST",
  route="/api/v1/orders",
  status="201"
}
```

---

# 8. Request Duration

Catat latency HTTP request.

Gunakan histogram untuk memungkinkan analisis percentile seperti:

```text
P50
P90
P95
P99
```

Contoh konsep:

```text
http_request_duration_seconds
```

Jangan menggunakan `trace_id`, `request_id`, `user_id`, atau `order_id` sebagai metric label.

---

# 9. Error Metrics

Catat request yang menghasilkan error.

Minimal dapat dibedakan berdasarkan:

```text
route
status
method
```

Contoh:

```text
http_errors_total{
  method="POST",
  route="/api/v1/orders",
  status="500"
}
```

HTTP 4xx dan 5xx harus dapat dianalisis.

Jika diperlukan, 4xx dan 5xx dapat dibedakan menggunakan status class atau metric yang berbeda.

---

# 10. Cardinality Policy

Ini merupakan requirement penting.

**Jangan menggunakan high-cardinality labels.**

Dilarang menggunakan:

```text
trace_id
request_id
user_id
customer_id
merchant_id
courier_id
order_id
email
phone
IP address
```

sebagai Prometheus label.

Gunakan label yang bounded:

```text
method
route
status
```

Route harus menggunakan normalized route pattern jika framework memungkinkan.

Gunakan:

```text
/api/v1/orders/{id}
```

bukan:

```text
/api/v1/orders/01JXYZ...
```

Agar jumlah time series tetap terkendali.

---

# 11. Route Normalization

Metrics harus menghindari dynamic URL sebagai label.

Bad:

```text
route="/api/v1/orders/123"
route="/api/v1/orders/456"
route="/api/v1/orders/789"
```

Good:

```text
route="/api/v1/orders/{id}"
```

Jika route name tersedia dan stabil, gunakan route name sebagai label atau metric dimension.

---

# 12. Prometheus Configuration

Buat configuration Prometheus yang sederhana dan mudah dikembangkan.

Contoh target:

```yaml
scrape_configs:
    - job_name: jualantar-api
      metrics_path: /metrics
      static_configs:
          - targets:
                - api:8000
```

Nilai port, hostname, dan interval harus mengikuti environment Docker/project yang sebenarnya.

Jangan hardcode production-specific infrastructure.

---

# 13. Docker Compose

Prometheus harus dapat dijalankan menggunakan Docker Compose untuk development.

Target:

```text
docker-compose
├── api
├── postgres
├── mailpit
└── prometheus
```

Jika project sudah menggunakan compose file terpisah, pertahankan convention tersebut.

Prometheus harus memiliki persistent storage jika metrics retention diperlukan.

Contoh:

```text
prometheus_data
```

Jangan menyimpan data Prometheus di container filesystem tanpa volume pada environment yang membutuhkan persistence.

---

# 14. Environment Configuration

Configuration harus mudah disesuaikan antar environment.

Contoh:

```text
PROMETHEUS_ENABLED=true
PROMETHEUS_PATH=/metrics
```

Jika configuration tersebut tidak diperlukan oleh implementation/library yang digunakan, jangan menambahkannya hanya untuk memenuhi dokumen.

Hindari configuration duplication.

---

# 15. Application Metrics

Phase 1B fokus utama pada HTTP metrics.

Application/business metrics hanya ditambahkan jika sudah memiliki definisi yang stabil.

Contoh future metrics:

```text
orders_created_total
orders_completed_total
orders_cancelled_total
courier_online_total
merchant_active_total
```

Metrics tersebut **tidak wajib untuk Phase 1B**.

Jangan menambahkan business metrics tanpa kebutuhan yang jelas.

---

# 16. Authentication & Metrics Endpoint

Metrics endpoint harus dipertimbangkan sebagai infrastructure endpoint.

Target:

```text
Application API
    ↓
/metrics
    ↓
Prometheus
```

Jangan memberikan metrics endpoint akses publik tanpa alasan.

Jika API deployment memiliki reverse proxy/network layer, prefer:

```text
Internet
   X
   │
   │ blocked
   ▼
Internal Network
   │
   └── Prometheus → API /metrics
```

---

# 17. Performance

Metrics collection harus memiliki overhead minimal.

Requirements:

- Jangan melakukan database query untuk setiap metric.
- Jangan melakukan external network call saat recording metric.
- Jangan menyimpan metric data dalam application database.
- Jangan membuat metric label berdasarkan input user.
- Gunakan in-memory instrumentation/client library yang sesuai.

---

# 18. Testing

Tambahkan automated tests.

## Metrics Endpoint

Test:

- `GET /metrics` tersedia.
- Response menggunakan format Prometheus.
- Endpoint tidak menghasilkan sensitive data.
- Metrics dapat ditemukan setelah request dilakukan.

## Request Metrics

Test:

- Request meningkatkan request counter.
- HTTP method tercatat.
- Route tercatat.
- Status tercatat.

## Duration

Test:

- Request duration tercatat.
- Metric menggunakan histogram/distribution yang sesuai.

## Errors

Test:

- 4xx request dapat tercatat.
- 5xx request dapat tercatat.
- Error metrics tetap tersedia setelah exception handling.

## Cardinality

Test atau review:

- Tidak ada user-specific identifier pada metric labels.
- Tidak ada request-specific identifier pada metric labels.
- Dynamic URL tidak menghasilkan series berbeda untuk setiap resource ID.

---

# 19. Health Check

Pastikan:

```text
GET /up
```

tetap bekerja.

Jika `/up` ikut menghasilkan metrics, behavior tersebut harus dipahami dan tidak menimbulkan noise yang tidak diperlukan.

Health check tidak boleh bergantung pada Prometheus agar tetap dapat digunakan untuk container/service health checking.

---

# 20. Failure Behavior

Prometheus failure tidak boleh membuat API gagal.

Jika Prometheus:

```text
DOWN
```

API tetap harus dapat:

```text
serve requests
process business logic
return responses
```

Metrics collection harus bersifat non-blocking terhadap business request.

---

# 21. Security

Metrics tidak boleh membocorkan:

```text
password
tokens
cookies
authorization headers
API keys
database credentials
environment secrets
personal sensitive data
request body
response body
```

Metrics hanya berisi aggregated operational data.

---

# 22. Maintainability

Implementation harus:

- Reusable.
- Testable.
- Framework-aligned.
- Tidak mencampurkan metrics code dengan business logic jika dapat dihindari.
- Mudah ditambah metric baru.
- Mudah dipindahkan/diintegrasikan ke Grafana.
- Tidak mengunci application pada dashboard tertentu.

Gunakan library yang mature dan sesuai dengan Laravel/PHP ecosystem jika diperlukan.

Jangan membuat Prometheus client/instrumentation dari scratch jika library yang sesuai sudah tersedia.

---

# 23. Observability Relationship

Phase 1A:

```text
API
 ├── Logs
 ├── Request ID
 ├── Exceptions
 └── Metrics Foundation
```

Phase 1B:

```text
API
 └── Metrics
       ↓
   Prometheus
```

Future:

```text
Prometheus
     ↓
  Grafana
```

Logging dan metrics tetap menjadi dua concern yang berbeda:

```text
Logs   → detailed event/debug information
Metrics → aggregated numerical measurements
```

---

# 24. Out of Scope

Tidak termasuk Phase 1B:

```text
Grafana
Loki
Tempo
OpenTelemetry
Distributed tracing
Alertmanager
Advanced alerting
Business analytics dashboard
Real-time operations dashboard
Kubernetes monitoring
Cloud managed monitoring
```

Komponen tersebut akan dipertimbangkan pada phase berikutnya.

---

# 25. Implementation Order

Implementasikan secara bertahap:

```text
1. Audit Phase 1A observability implementation
        ↓
2. Select/confirm Prometheus client/library
        ↓
3. Define metric naming conventions
        ↓
4. Define label/cardinality policy
        ↓
5. Implement /metrics endpoint
        ↓
6. Implement HTTP request counter
        ↓
7. Implement HTTP duration histogram
        ↓
8. Implement HTTP error metrics
        ↓
9. Configure Prometheus scraping
        ↓
10. Add Docker Compose integration
        ↓
11. Add automated tests
        ↓
12. Validate metrics output
        ↓
13. Run full test suite
        ↓
14. Document usage
```

---

# 26. Acceptance Criteria

## Prometheus

- [x] Prometheus dapat dijalankan melalui Docker Compose.
- [x] Prometheus dapat melakukan scrape JualAntar API.
- [x] Target API berstatus healthy pada Prometheus.

## Metrics Endpoint

- [x] `GET /metrics` tersedia.
- [x] Response menggunakan Prometheus exposition format.
- [x] Endpoint tidak mengekspos sensitive information.

## HTTP Metrics

- [x] Request count tersedia.
- [x] Request duration tersedia.
- [x] Error metrics tersedia.
- [x] Method tersedia sebagai bounded label.
- [x] Route tersedia sebagai normalized/bounded label.
- [x] Status tersedia sebagai bounded label.

## Cardinality

- [x] Tidak ada `trace_id` sebagai label.
- [x] Tidak ada `request_id` sebagai label.
- [x] Tidak ada user/customer/merchant/courier/order ID sebagai label.
- [x] Tidak ada email/phone/IP sebagai label.
- [x] Dynamic route tidak menghasilkan uncontrolled time series.

## Reliability

- [x] API tetap berfungsi ketika Prometheus tidak tersedia.
- [x] Metrics collection tidak melakukan database query per request.
- [x] Metrics collection tidak membuat business request blocking.

## Testing

- [x] Metrics endpoint tests passing.
- [x] Request metrics tests passing.
- [x] Duration metrics tests passing.
- [x] Error metrics tests passing.
- [x] Existing Phase 1A tests tetap passing.
- [ ] Full test suite passing.

---

# 27. Definition of Done

Phase 1B dianggap selesai ketika:

```text
HTTP Metrics
      +
Prometheus Endpoint
      +
Prometheus Scraping
      +
Cardinality Control
      +
Docker Compose
      +
Automated Tests
      ↓
Prometheus Metrics Foundation Ready
```

Pada titik ini JualAntar API sudah dapat menyediakan operational metrics yang siap divisualisasikan menggunakan Grafana pada Phase 2.

---

# 28. Future Evolution

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
OpenTelemetry + Tempo
        ↓
Production Monitoring
& Alerting
```

Phase 1B harus tetap sederhana: **ukur API dengan benar terlebih dahulu, kemudian visualisasikan dan alert-kan metrics pada fase berikutnya.**
