# AI_USAGE

## Tools & model yang dipakai

| Tool | Dipakai untuk |
|---|---|
| **Claude Code** (Anthropic, keluarga model Claude) — sesi cloud | Scaffolding proyek, menulis sebagian besar kode backend & frontend, test, Dockerfile, CI, dan draft dokumentasi |

## Bagian yang dikerjakan dengan bantuan AI

Secara jujur: **sebagian besar kode awal di repository ini dihasilkan dengan bantuan Claude Code**, lalu saya
review, jalankan, dan uji. Rinciannya:

| Area | Bantuan AI | Verifikasi yang dilakukan |
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

## Bagian yang saya tulis atau ubah sendiri

Catatan perubahan manual saya (di luar output AI) dicatat di tabel ini seiring pengerjaan dan latihan:

| Tanggal | File / fungsi | Perubahan |
|---|---|---|
| | | |

## Bagaimana saya memastikan memahami kode

- Membaca ulang setiap use case dari route → Form Request → Policy → Action → Service → Model.
- Menjalankan test dan sengaja merusak bagian penting (lock, state machine) untuk melihat test yang gagal.
- Latihan live change (menambah fitur kecil + test) pada repository ini.
