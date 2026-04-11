<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 4);
            $table->string('movement_type', 32);
            $table->text('notes')->nullable();
            $table->nullableMorphs('reference');
            $table->timestamps();

            $table->index(['tenant_id', 'product_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
