# DECISIONS

Jawaban atas 4 pertanyaan wajib, dengan rujukan file/fungsi di repository.

## a. Bagaimana mencegah stok minus saat dua order masuk bersamaan?

Tiga lapis:

1. **Lock baris produk sebelum membaca stok.**
   `App\Services\StockService::lockProducts()` menjalankan
   `SELECT … FROM products WHERE id IN (…) ORDER BY id FOR UPDATE` di dalam `DB::transaction`
   (dipanggil dari `App\Actions\Order\CreateOrderAction::execute()`).
   Transaksi kedua untuk produk yang sama **menunggu** sampai transaksi pertama commit, lalu membaca stok
   yang sudah berkurang. Validasi "stok cukup?" dilakukan *setelah* lock, jadi angkanya tidak bisa berubah
   sampai commit.
2. **Urutan lock berdasarkan `id`** (`sort($ids)` + `orderBy('id')`), sehingga order multi-item dengan urutan
   item berbeda (`[A,B]` vs `[B,A]`) tidak saling menunggu melingkar → tidak deadlock.
3. **CHECK constraint** `products_stock_non_negative CHECK (stock >= 0)` di migration
   `2026_11_04_000100_create_products_table.php` sebagai jaring pengaman terakhir; plus guard di
   `StockService::move()`.

Pembatalan memakai pola yang sama pada baris **order** (`TransitionOrderAction::execute()` →
`lockForUpdate()` lalu baca ulang status), sehingga cancel dua kali tidak mengembalikan stok dua kali.

Bukti: `tests/Concurrency/StockConcurrencyTest.php` — 20 proses paralel (`pcntl_fork`, koneksi DB masing-masing)
memesan produk stok 5 → tepat 5 sukses, 15 gagal 422, stok akhir 0, ledger 5 baris.

## b. Mengapa struktur folder & layer seperti ini, dan apa yang diubah jika aplikasi membesar?

- **Controller tipis** (`app/Http/Controllers/Api`) hanya menerima input tervalidasi, memanggil use case,
  lalu mengembalikan Resource.
- **Form Request** (`app/Http/Requests`) = validasi + otorisasi yang jalan *sebelum* controller.
- **Action** (`app/Actions`) = satu use case, pemilik batas transaksi. Seeder memakai Action yang sama
  sehingga data seed konsisten dengan data dari API.
- **Domain murni** (`app/Domain/Order`) = aturan bisnis tanpa framework (state machine, kalkulasi uang) →
  unit test cepat.
- **Service** (`app/Services`) = kemampuan teknis yang dipakai ulang beberapa use case (lock + ledger stok,
  idempotency, nomor order, laporan, cache dashboard).
- **Policy** (`app/Policies`) = RBAC terpusat.

Saya sengaja tidak memakai Repository pattern / DDD penuh karena untuk ukuran aplikasi ini Eloquent sudah cukup
dan lapisan tambahan hanya menambah indireksi.

Jika aplikasi membesar: pecah per **modul bisnis** (`app/Modules/Order`, `app/Modules/Inventory`,
`app/Modules/Reporting`) masing-masing berisi Actions/Models/Policies/Http sendiri; komunikasi antar modul lewat
event (`OrderPaid`, `StockLow`) dan outbox; laporan dipindah ke read model/replica; stok per cabang
(lihat ARCHITECTURE.md bagian 4).

## c. Satu bug/masalah yang ditemui dan bagaimana menemukannya

**Kasir mendapat 422 (bukan 403) saat memanggil endpoint khusus admin dengan payload tidak valid.**

Ditemukan saat smoke test manual dengan `curl`: login sebagai kasir, `POST /api/products/1/stock-adjustments`
dengan `note` terlalu pendek → response `422 Validasi gagal` padahal seharusnya `403`. Penyebabnya: Laravel
me-resolve Form Request (validasi) **sebelum** baris `Gate::authorize()` di dalam controller dijalankan.
Akibatnya user yang tidak berhak bisa "memancing" pesan validasi.

Perbaikan: otorisasi dipindah ke `FormRequest::authorize()` (`StockAdjustmentRequest`, `StoreProductRequest`,
`UpdateProductRequest`, `ReportRequest`, `CancelOrderRequest`) — Laravel menjalankan `authorize()` sebelum
`rules()`. Ditambah test regresi:
`tests/Feature/StockAdjustmentTest.php` ("kasir dengan payload invalid tetap dapat 403") dan
`tests/Feature/OrderTransitionTest.php` ("kasir cancel tanpa alasan tetap 403").

Masalah lain yang dicatat: saat memverifikasi bahwa concurrency test benar-benar bermakna, saya menghapus
sementara `lockForUpdate()` — hasilnya 20/20 order sukses untuk stok 5 (*lost update*), dan CHECK constraint
tidak menangkapnya karena nilai yang ditulis tetap positif. Ini alasan lock tetap diperlukan walaupun ada constraint.

Celah test yang ditemukan dengan *bug injection*: saya sengaja mengubah `StockService::move()` menjadi
`NotifyLowStock::dispatch(...)` tanpa `->afterCommit()` dan tanpa syarat "melewati batas" — **seluruh test tetap hijau**.
Ternyata `Queue::fake()` mencatat job seketika sehingga tidak bisa membuktikan perilaku *after commit*.
Ditambahkan dua test: notifikasi tidak dikirim jika stok sudah di bawah batas sebelumnya
(`tests/Feature/OrderCreationTest.php`), dan notifikasi tidak dikirim jika transaksi rollback — memakai queue
`sync` + `Notification::fake()` yang benar-benar mengikuti commit/rollback (`tests/Feature/JobTest.php`).

Test *flaky* yang ditemukan saat menyusun ulang riwayat commit: `CustomerTest` "search pelanggan" membuat
pelanggan kedua dengan email/telepon acak dari Faker (locale `id_ID`), yang sesekali mengandung kata "budi",
sehingga hasil pencarian berisi 2 baris. Diperbaiki dengan data eksplisit di test (`tests/Feature/CustomerTest.php`).

## d. Bagian yang belum selesai / masih lemah, dan langkah selanjutnya

| Bagian | Kondisi | Langkah berikutnya |
|---|---|---|
| Jendela crash idempotency | Order sudah commit tapi response belum tersimpan → retry setelah 60 detik bisa memproses ulang | Kolom `orders.idempotency_key` UNIQUE |
| Email invoice | *At-least-once* (bisa terkirim dua kali jika worker mati di titik tertentu) | Outbox + Message-ID deterministik |
| Token di `localStorage` | Rentan XSS | Sanctum SPA cookie HttpOnly jika domain memungkinkan |
| Invoice | HTML, belum PDF | dompdf di job yang sama |
| Dropdown di form order | Memuat 100 data pertama | Combobox dengan search server-side |
| Bonus | Audit log, export CSV, E2E Playwright di CI, codegen type OpenAPI, metrics | Lihat ARCHITECTURE.md bagian 6 |
| Lokalisasi | Pesan validasi bawaan masih bahasa Inggris | Tambah `lang/id/validation.php` |
