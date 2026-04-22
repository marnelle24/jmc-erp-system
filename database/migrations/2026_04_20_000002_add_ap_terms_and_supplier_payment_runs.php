<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_payable', function (Blueprint $table): void {
            $table->string('invoice_number')->nullable()->after('supplier_id');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->date('due_date')->nullable()->after('invoice_date');
            $table->unsignedSmallInteger('payment_terms_days')->nullable()->after('due_date');
            $table->unsignedTinyInteger('priority')->default(3)->after('payment_terms_days');

            $table->index(['tenant_id', 'due_date']);
            $table->index(['tenant_id', 'priority']);
            $table->index(['tenant_id', 'supplier_id', 'due_date']);
        });

        Schema::create('supplier_payment_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('reference_code', 64)->unique();
            $table->string('status', 32)->default('draft');
            $table->date('scheduled_for');
            $table->string('payment_method', 32)->nullable();
            $table->decimal('proposed_amount', 15, 4)->default(0);
            $table->decimal('approved_amount', 15, 4)->default(0);
            $table->decimal('executed_amount', 15, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'scheduled_for']);
        });

        Schema::create('supplier_payment_run_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_payment_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounts_payable_id')->constrained('accounts_payable')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->decimal('planned_amount', 15, 4);
            $table->decimal('executed_amount', 15, 4)->default(0);
            $table->foreignId('supplier_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['supplier_payment_run_id', 'accounts_payable_id']);
            $table->index(['tenant_id', 'supplier_payment_run_id']);
            $table->index(['tenant_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_run_items');
        Schema::dropIfExists('supplier_payment_runs');

        Schema::table('accounts_payable', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'due_date']);
            $table->dropIndex(['tenant_id', 'priority']);
            $table->dropIndex(['tenant_id', 'supplier_id', 'due_date']);

            $table->dropColumn([
                'invoice_number',
                'invoice_date',
                'due_date',
                'payment_terms_days',
                'priority',
            ]);
        });
    }
};
