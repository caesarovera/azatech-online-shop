<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ledger append-only: baris tidak pernah di-update/di-delete.
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->enum('type', ['INITIAL', 'ORDER_OUT', 'CANCEL_RESTORE', 'ADJUSTMENT']);
            $table->integer('quantity_change'); // bertanda: -2, +2, +50
            $table->integer('stock_after');
            $table->string('reference_type', 30)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Riwayat per produk, urut terbaru.
            $table->index(['product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
