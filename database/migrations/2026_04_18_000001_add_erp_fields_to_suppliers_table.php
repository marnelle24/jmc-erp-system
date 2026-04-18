<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('code', 64)->nullable()->after('name');
            $table->string('status', 32)->default('active')->after('code');
            $table->string('payment_terms', 128)->nullable()->after('address');
            $table->string('tax_id', 64)->nullable()->after('payment_terms');
            $table->text('notes')->nullable()->after('tax_id');

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn(['code', 'status', 'payment_terms', 'tax_id', 'notes']);
        });
    }
};
