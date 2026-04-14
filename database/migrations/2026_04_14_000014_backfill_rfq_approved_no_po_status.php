<?php

use App\Enums\RfqStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rfqs')
            ->whereNotNull('approved_by')
            ->where('status', RfqStatus::PendingForApproval->value)
            ->update(['status' => RfqStatus::ApprovedNoPo->value]);
    }

    public function down(): void
    {
        DB::table('rfqs')
            ->whereNotNull('approved_by')
            ->where('status', RfqStatus::ApprovedNoPo->value)
            ->update(['status' => RfqStatus::PendingForApproval->value]);
    }
};
