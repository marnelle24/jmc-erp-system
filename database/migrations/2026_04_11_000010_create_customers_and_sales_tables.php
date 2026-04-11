<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->date('order_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'order_date']);
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_ordered', 15, 4);
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('sales_order_id');
        });

        Schema::create('sales_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->dateTime('shipped_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['sales_order_id', 'status']);
        });

        Schema::create('sales_shipment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_line_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_shipped', 15, 4);
            $table->timestamps();

            $table->index('sales_shipment_id');
            $table->index('sales_order_line_id');
        });

        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->restrictOnDelete();
            $table->string('status', 32);
            $table->dateTime('issued_at');
            /** Customer-facing reference for AR matching (Phase 5). */
            $table->string('customer_document_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['sales_order_id', 'status']);
        });

        Schema::create('sales_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_line_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_invoiced', 15, 4);
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->timestamps();

            $table->index('sales_invoice_id');
            $table->index('sales_order_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_invoices');
        Schema::dropIfExists('sales_shipment_lines');
        Schema::dropIfExists('sales_shipments');
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('customers');
    }
};
