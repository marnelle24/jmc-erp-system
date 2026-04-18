<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            /** Actual landed unit cost for this receipt line (may differ from PO estimate). */
            $table->decimal('unit_cost', 15, 4)->nullable()->after('quantity_received');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
