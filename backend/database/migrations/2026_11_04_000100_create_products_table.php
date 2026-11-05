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
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_code', 30)->unique();
            $table->string('product_name');
            $table->integer('stock')->default(0);
            $table->decimal('price', 12, 2);
            $table->integer('low_stock_threshold')->default(5);
            $table->timestamps();
            $table->softDeletes();
        });

        // Lapisan pertahanan terakhir: walaupun ada bug di aplikasi, DB menolak stok minus.
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_non_negative CHECK (stock >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_positive CHECK (price > 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_threshold_non_negative CHECK (low_stock_threshold >= 0)');

        // Search "ILIKE %keyword%" tidak bisa memakai index B-tree biasa → pakai trigram (GIN).
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX products_name_trgm_idx ON products USING gin (product_name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
