<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('notes');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            $table->text('close_reason')->nullable()->after('closed_by');

            $table->index(['tenant_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'closed_at']);
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['closed_at', 'close_reason']);
        });
    }
};
