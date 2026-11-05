# AI_USAGE

## Tools & model yang dipakai

| Tool | Dipakai untuk |
|---|---|
| **Claude Code** (Anthropic, keluarga model Claude) — sesi cloud | Scaffolding proyek, menulis sebagian besar kode backend & frontend, test, Dockerfile, CI, dan draft dokumentasi |

## Latar belakang & kapasitas saya

Saya PHP developer di level middle. Soal ini ditulis untuk level senior, dan topik yang paling menantang
(pessimistic locking, idempotency, concurrency test, Docker multi-stage) sedang saya pelajari. Karena itu
**hampir seluruh kode, test, konfigurasi infrastruktur, dan draf dokumentasi di repository ini dihasilkan oleh
Claude Code**. Peran saya adalah mengambil keputusan, mempelajari kodenya, dan menjawab pertanyaan DECISIONS.md.

## Bagian yang dikerjakan dengan bantuan AI

Verifikasi di tabel ini dilakukan oleh Claude Code selama sesi pengerjaan:

| Area | Bantuan AI | Verifikasi yang dilakukan (oleh AI) |
|---|---|---|
| Migration, model, enum, factory, seeder | Dihasilkan AI | `migrate:fresh --seed` di PostgreSQL; cek ledger = stok untuk semua produk (query SQL) |
| State machine, kalkulator total | Dihasilkan AI | Unit test semua transisi valid/invalid/role |
| Order + locking + ledger + idempotency | Dihasilkan AI | Concurrency test dengan proses paralel sungguhan; **mutation check**: `lockForUpdate()` dihapus sementara → test gagal (20/20 sukses = overselling), lalu dikembalikan |
| Auth, RBAC, rate limit, logging, health | Dihasilkan AI | Feature test + smoke test `curl`; dari sini ditemukan bug 422-vs-403 (lihat DECISIONS.md c) |
| Job invoice & notifikasi | Dihasilkan AI | Dijalankan via Redis queue + Mailpit di `docker compose`; `request_id` terlihat ikut ke log worker |
| Frontend Vue | Dihasilkan AI | Vitest, `vue-tsc`, ESLint, uji alur UI dengan Playwright (login → buat order → error stok per item → PAID → invoice) |
| Docker, Compose, CI | Dihasilkan AI | `docker compose up` lokal: semua service healthy, email masuk Mailpit |
| README, ARCHITECTURE, DECISIONS | Draft oleh AI, disesuaikan dengan kode | Dicek ulang bahwa semua file/fungsi yang dirujuk benar-benar ada |

## Prompt utama (disalin apa adanya)

1. Prompt pembuka (beserta lampiran PDF soal):

   > menurut mu dari technical test tersebut dengan pengalaman saya sebagai php developer di tingkat middle agar aman dan bisa menjelaskan semua kode dan arsitektur yang dibuat dan nanti jika disuruh live code perubahan bagaimana, dan ini sebaiknya berikan saya pelatihan mock up test untuk live code agar saya bisa menjelaskan, sertakan dokumen yang lengkap dan push ke repo github saya. Saya pilih track B Laravel

2. Jawaban atas pertanyaan klarifikasi dari AI (dipilih dari opsi):

   > Repo: azatech-online-shop · Materi latihan: repo private terpisah · Frontend: Vue 3 + Vite

## Tentang riwayat commit

Seluruh kode dihasilkan dalam satu sesi Claude Code. Setelah kode selesai, riwayat commit **disusun ulang oleh
Claude Code menjadi satu commit per tugas soal** (nomor bagian soal tercantum di pesan commit, mis. `4.3`, `4.4`).
Setiap commit diverifikasi dengan menjalankan test pada saat disusun. Karena itu waktu commit berdekatan dan
tidak menggambarkan durasi pengerjaan tiap tugas.

## Bagian yang saya kerjakan atau putuskan sendiri

| Bagian | Yang saya lakukan |
|---|---|
| Pilihan stack | Memilih Track B (Laravel) dan Vue 3 + Vite untuk frontend |
| Repository | Membuat repository, mengatur default branch ke `main`, menentukan riwayat commit dan branch disusun per tugas soal |
| DECISIONS.md | Menjawab keempat pertanyaan lewat tanya-jawab dengan AI. Sebagian kalimat di poin (a) dan (c) mengikuti penjelasan AI yang sudah saya pahami, sisanya kata-kata saya sendiri. AI merapikan ejaan, menambahkan rujukan file/fungsi, dan mengecek kecocokan dengan kode. Untuk poin (c), bug-nya ditemukan oleh AI saat smoke test; penjelasannya saya tulis sendiri |

## Rencana saya sebelum sesi review

- Menjalankan aplikasi dengan `docker compose up` dan mencoba alur order sebagai admin dan kasir.
- Membaca alur `POST /api/orders` dari route → Form Request → Action → `StockService` → database.
- Latihan perubahan kecil (live change) beserta test-nya di repository ini.
