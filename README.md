# Azatech Online Shop — Order Management

[![CI](https://github.com/caesarovera/azatech-online-shop/actions/workflows/ci.yml/badge.svg)](https://github.com/caesarovera/azatech-online-shop/actions/workflows/ci.yml)

Technical test **Senior Fullstack Developer — Track B (Laravel)**.
Sistem online shop untuk mengelola produk, pelanggan, dan order multi-item dengan state machine status order,
dua role (admin & cashier), stock ledger, idempotency, dan pemrosesan asinkron — tetap konsisten ketika banyak
kasir membuat order bersamaan.

| Dokumen | Isi |
|---|---|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Diagram, layer, ADR (stok, idempotency, auth, dll), skala 50.000 order/hari |
| [DECISIONS.md](DECISIONS.md) | Jawaban 4 pertanyaan wajib (concurrency, struktur, bug, kekurangan) |
| [AI_USAGE.md](AI_USAGE.md) | Penggunaan AI assistant |
| [docs/openapi.json](docs/openapi.json) | Spesifikasi OpenAPI 3 (hasil export Scramble) |

---

## Tech stack & alasan

| Komponen | Pilihan | Alasan |
|---|---|---|
| Backend | **PHP 8.4, Laravel 13**, `declare(strict_types=1)` | Track B; ekosistem lengkap (queue, policy, form request) |
| Database | **PostgreSQL 17** | `SELECT … FOR UPDATE`, CHECK constraint, `ON CONFLICT`, `FILTER`, trigram index |
| Auth | **Laravel Sanctum** (API token + expiry + revoke) | Sesuai Track B, sederhana, token tersimpan di DB sehingga bisa di-revoke |
| Queue & cache | **Redis** (Laravel Queue) | Wajib untuk level senior; dipakai untuk job invoice/notifikasi & cache dashboard |
| Mail lokal | **Mailpit** | SMTP lokal + UI untuk melihat email |
| API docs | **Scramble** (OpenAPI 3, generate otomatis dari kode) | Dokumentasi selalu sinkron dengan Form Request & Resource |
| Kualitas | **Pest**, **Larastan level 6**, **Laravel Pint** | Test, static analysis, code style |
| Frontend | **Vue 3 + Vite + TypeScript (strict)** | Lebih ringkas daripada Next.js untuk SPA admin; Composition API |
| Server state | **TanStack Vue Query** | Cache + invalidasi setelah mutasi |
| Validasi form | **Zod** | Skema type-safe, path error sama dengan backend (`items.0.quantity`) |
| UI | **Tailwind CSS v4**, Chart.js | Cepat, tanpa komponen berat |
| Test frontend | **Vitest + Vue Test Utils** | Component test untuk form order |
| Infra | Docker multi-stage (non-root), Docker Compose, GitHub Actions | |

## Quick start

Prasyarat: Docker + Docker Compose v2.

```bash
docker compose up -d --build
```

Perintah di atas menjalankan **postgres, redis, mailpit, migrate (migrasi + seed otomatis), api, worker, scheduler, web**.
Tidak perlu file `.env` — semua punya default lokal. (Opsional: `cp .env.example .env` untuk mengganti nilai.)

| Service | URL |
|---|---|
| Frontend | http://localhost:5173 |
| API | http://localhost:8000/api |
| OpenAPI (Scramble UI) | http://localhost:8000/docs/api |
| OpenAPI JSON | http://localhost:8000/docs/api.json |
| Health check | http://localhost:8000/api/health |
| Mailpit (email invoice) | http://localhost:8025 |

### Akun login (hasil seeder)

| Role | Email | Password |
|---|---|---|
| admin | `admin@azatech.id` | `password` |
| cashier | `cashier@azatech.id` | `password` |

Seeder juga membuat 50 produk (5 di antaranya stok rendah), 30 pelanggan, dan 150 order berbagai status
dalam 90 hari terakhir. Seeder **memakai Action yang sama dengan API**, sehingga ledger stok, nomor order,
dan histori status hasil seed konsisten.

Reset data: `docker compose down -v && docker compose up -d`.

## Menjalankan tanpa Docker (development)

```bash
# Backend (butuh PHP 8.3+, PostgreSQL, Redis)
cd backend
cp .env.example .env            # sesuaikan DB_*
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve               # http://localhost:8000
php artisan queue:work          # terminal lain

# Frontend
cd frontend
cp .env.example .env
npm install
npm run dev                     # http://localhost:5173
```

## Menjalankan test

Test backend **wajib PostgreSQL sungguhan** (bukan SQLite) karena perilaku locking berbeda.

```bash
cd backend
# buat database test sekali saja: CREATE DATABASE azatech_test;
vendor/bin/pest                          # semua: unit + feature + concurrency
vendor/bin/pest --testsuite=Concurrency  # hanya test concurrency (proses paralel sungguhan)
vendor/bin/pint --test                   # code style
vendor/bin/phpstan analyse               # Larastan level 6

cd ../frontend
npm run lint && npm run type-check && npm test && npm run build
```

| Suite | Isi |
|---|---|
| `tests/Unit` | State machine order (semua transisi valid/invalid/role), kalkulasi total (tanpa floating point error) |
| `tests/Feature` | Auth (expiry, revoke, rate limit), CRUD + RBAC per role, order, transisi, idempotency, ledger, laporan, cache, job, observability |
| `tests/Concurrency` | 20 order paralel untuk stok 5 → tepat 5 sukses / 15 gagal / stok 0; multi-item urutan terbalik tanpa deadlock; 10 cancel paralel → stok kembali sekali; 8 request paralel dengan Idempotency-Key sama → 1 order |
| `frontend/src/**/__tests__` | Form order: total real-time, validasi Zod, produk duplikat, error server per item, cegah double submit |

> Concurrency test memakai `pcntl_fork()` — tiap proses punya koneksi PostgreSQL sendiri, jadi lock
> `FOR UPDATE` benar-benar diuji. Jika `lockForUpdate()` di `StockService` dihapus, test ini gagal
> (20 dari 20 order sukses → overselling).

## Ringkasan fitur

**Backend**
- Auth Sanctum: login, logout (revoke token), `/me`, token expiry (default 8 jam), rate limit login 5/menit.
- CRUD produk & pelanggan: pagination, search (index trigram), sort (whitelist), soft delete, Form Request.
- Order multi-item dalam **satu transaksi**: lock produk berurutan, validasi stok semua item, harga & total dari server,
  snapshot `unit_price`, `order_number` unik `INV-YYYYMMDD-NNNN`, stok langsung direservasi.
- **State machine** status order di `app/Domain/Order/OrderStateMachine.php` → transisi invalid = 409, role tidak berhak = 403.
- **Idempotency-Key** untuk `POST /api/orders` (replay, 422 untuk payload berbeda, 409 saat masih diproses, TTL 24 jam).
- **Stock ledger** (`stock_movements`) untuk order, cancel, adjustment, stok awal; endpoint adjustment & riwayat.
- **RBAC** di backend via Policy / Gate / `FormRequest::authorize()`.
- **Background job** (Redis): invoice HTML + email ke customer saat PAID (retry + backoff, idempotent),
  notifikasi stok menipis ke admin (database + email, unique job).
- **Laporan** sales per hari/bulan & produk terlaris (agregasi di DB), **dashboard summary** di-cache Redis.
- **Security & observability**: rate limit, CORS eksplisit, mass-assignment protection, JSON structured log dengan
  `request_id` (ikut terbawa ke job), `GET /api/health`, error 500 tanpa stack trace di production.

**Frontend**
- Login, route guard, menu & tombol berbasis role, logout paksa saat token expired (401).
- Tabel produk/pelanggan/order dengan pagination, search, sort, filter yang **tersinkron ke URL**.
- Buat order: baris dinamis, total real-time, Zod, error server per item, Idempotency-Key per percobaan submit, anti double submit.
- Detail order: timeline status, tombol aksi dari `available_actions` server, form alasan batal, unduh invoice.
- Stock adjustment & ledger per produk, laporan dengan grafik & tabel produk terlaris.
- Invalidasi cache Vue Query setelah mutasi (stok ter-update setelah order dibuat).

## Struktur repository

```
.
├── backend/                 Laravel 13 API
│   ├── app/
│   │   ├── Actions/         Use case (CreateOrder, TransitionOrder, AdjustStock, CreateProduct)
│   │   ├── Domain/Order/    State machine & kalkulator total (PHP murni)
│   │   ├── Services/        StockService, IdempotencyService, OrderNumberGenerator, Report, Dashboard, Invoice
│   │   ├── Http/            Controllers (tipis), Requests (validasi+otorisasi), Resources, Middleware
│   │   ├── Policies/        RBAC
│   │   ├── Jobs/ Mail/ Notifications/
│   │   └── Exceptions/      DomainException + ApiExceptionRenderer (format error standar)
│   ├── database/            migrations, factories, seeders
│   └── tests/               Unit, Feature, Concurrency
├── frontend/                Vue 3 + Vite + TS
├── docs/openapi.json
├── docker-compose.yml
└── .github/workflows/ci.yml
```

## Belum selesai / keterbatasan (jujur)

- **PDF invoice**: invoice berupa HTML (diizinkan soal). Untuk PDF akan memakai `barryvdh/laravel-dompdf` di job yang sama.
- **Refresh token**: Track B memakai Sanctum token tunggal dengan expiry 8 jam (sesuai soal). Tidak ada sliding session;
  ketika expired, frontend memaksa logout.
- **Token di `localStorage`**: rentan XSS. Alternatif yang lebih aman: Sanctum SPA cookie (HttpOnly) — lihat ADR-003.
- **Dropdown order** memuat 100 produk/pelanggan pertama; untuk data besar perlu combobox dengan search server-side.
- **E2E Playwright di CI**, **shared type dari OpenAPI (codegen)**, **audit log**, **export CSV**, **metrics Prometheus**
  belum dikerjakan (bonus). Rencana singkat ada di ARCHITECTURE.md bagian "Rencana berikutnya".
- Pesan validasi bawaan Laravel masih berbahasa Inggris (belum ada file `lang/id`).

## AI Usage

Lihat [AI_USAGE.md](AI_USAGE.md).
