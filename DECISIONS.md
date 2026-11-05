# DECISIONS

Jawaban saya untuk 4 pertanyaan wajib, beserta file/fungsi terkait di repository.

## a. Bagaimana mencegah stok minus saat dua order masuk bersamaan?

Kasir yang lebih dulu menekan "Buat Order" yang mendapatkan stoknya. Kasir kedua tidak langsung diproses, tapi
menunggu karena baris produk sedang dikunci. Setelah kunci didapat, stok dicek lagi, jadi kasir kedua melihat
stok yang sudah berkurang menjadi 0 dan ditolak dengan pesan "Stok tidak mencukupi" (422).

Di kode, penguncian ini memakai `lockForUpdate()` (SQL `SELECT ... FOR UPDATE`) di
`StockService::lockProducts()`, yang dipanggil dari `CreateOrderAction` di dalam satu transaksi database.

## b. Mengapa struktur folder & layer seperti ini, dan apa yang diubah jika aplikasi membesar?

Kode dipisah menjadi Controller, Action (`app/Actions`), Service (`app/Services`), dan Domain (`app/Domain`)
supaya lebih mudah di-maintain dan setiap bagian punya tugas yang spesifik. Contohnya, semua perubahan stok
lewat `StockService`, jadi selalu tercatat di ledger (tabel `stock_movements`).

Saat ini semua Action ada di satu folder. Jika aplikasi membesar, kode bisa dipecah per modul, misalnya modul
Order, modul Stok, dan modul Laporan.

## c. Satu bug/masalah yang ditemui dan bagaimana menemukannya

Kasir mendapatkan error validasi (422) terlebih dahulu sebelum cek role. Seharusnya role dicek dulu, apakah
boleh mengakses endpoint tersebut (kalau tidak, 403), baru setelah itu validasi. Masalah ini ditemukan saat
smoke test manual dengan `curl` (dibantu AI): login sebagai kasir lalu memanggil
`POST /api/products/{id}/stock-adjustments` dengan data yang salah.

Perbaikannya: cek role dipindah ke method `authorize()` di Form Request, misalnya
`StockAdjustmentRequest::authorize()`, karena Laravel menjalankan `authorize()` sebelum `rules()`. Ditambahkan
juga test "kasir dengan payload invalid tetap dapat 403" di `tests/Feature/StockAdjustmentTest.php`.

## d. Bagian yang belum selesai / masih lemah, dan langkah selanjutnya

1. Invoice sekarang masih HTML (`InvoiceRenderer`, dibuat oleh job `SendOrderPaidNotification`). Nanti saya ubah
   jadi PDF dengan library (misalnya dompdf).
2. Dropdown produk dan pelanggan di form order hanya memuat 100 data pertama
   (`frontend/src/views/OrderCreateView.vue`). Untuk data yang banyak, dibuatkan kolom pencarian.

Kelemahan lain yang lebih teknis dicatat di ARCHITECTURE.md bagian 6 "Rencana berikutnya".
