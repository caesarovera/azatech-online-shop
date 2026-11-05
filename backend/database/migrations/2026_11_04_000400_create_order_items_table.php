<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2); // snapshot harga saat transaksi
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();

            // foreignId() di PostgreSQL TIDAK otomatis membuat index → buat manual.
            $table->index('order_id');
            $table->index('product_id');
            // Satu produk hanya satu baris per order (item duplikat digabung di service).
            $table->unique(['order_id', 'product_id']);
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
