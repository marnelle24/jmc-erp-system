<?php

use App\Enums\RfqLineUnitType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('rfq_lines', function (Blueprint $table) {
            $table->string('unit_type', 32)->default(RfqLineUnitType::Piece->value)->after('quantity');
        });

        DB::table('rfq_lines')->update(['unit_type' => RfqLineUnitType::Piece->value]);

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->foreignId('rfq_line_id')->nullable()->after('purchase_order_id')->constrained('rfq_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rfq_line_id');
        });

        Schema::table('rfq_lines', function (Blueprint $table) {
            $table->dropColumn('unit_type');
        });

        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
