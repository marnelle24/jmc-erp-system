<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->string('reference_code', 32)->nullable()->after('supplier_id');
        });

        $seqByTenant = [];
        $rfqs = DB::table('rfqs')->orderBy('tenant_id')->orderBy('id')->get(['id', 'tenant_id']);
        foreach ($rfqs as $row) {
            $tenantId = (int) $row->tenant_id;
            $seqByTenant[$tenantId] = ($seqByTenant[$tenantId] ?? 0) + 1;
            $code = 'RFQ'.str_pad((string) $seqByTenant[$tenantId], 6, '0', STR_PAD_LEFT);
            DB::table('rfqs')->where('id', $row->id)->update(['reference_code' => $code]);
        }

        Schema::table('rfqs', function (Blueprint $table) {
            $table->string('reference_code', 32)->nullable(false)->change();
            $table->unique(['tenant_id', 'reference_code']);
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'reference_code']);
            $table->dropColumn('reference_code');
        });
    }
};
