<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rfqs')->where('status', 'draft')->update(['status' => 'pending_for_approval']);
    }

    public function down(): void
    {
        DB::table('rfqs')->where('status', 'pending_for_approval')->update(['status' => 'draft']);
    }
};
