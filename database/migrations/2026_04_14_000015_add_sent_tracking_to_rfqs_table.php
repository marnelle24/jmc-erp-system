<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->foreignId('sent_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->after('sent_by');
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_by');
            $table->dropColumn('sent_at');
        });
    }
};
