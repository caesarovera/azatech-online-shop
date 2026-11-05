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
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['PENDING', 'PAID', 'SHIPPED', 'COMPLETED', 'CANCELLED'])->default('PENDING');
            $table->decimal('total_amount', 14, 2);
            $table->timestamp('order_date');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            // Penanda idempotensi job email invoice (lihat SendOrderPaidNotification).
            $table->timestamp('invoice_sent_at')->nullable();
            $table->timestamps();

            // Laporan: WHERE status IN (...) AND paid_at BETWEEN ... → composite index.
            $table->index(['status', 'paid_at']);
            // List order: filter tanggal & customer, urut terbaru.
            $table->index('order_date');
            $table->index(['customer_id', 'order_date']);
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_non_negative CHECK (total_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
