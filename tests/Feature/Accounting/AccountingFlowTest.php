<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Services\PostAccountsPayableFromGoodsReceiptService;
use App\Domains\Accounting\Services\RecordSupplierPaymentService;
use App\Domains\Procurement\Services\PostGoodsReceiptService;
use App\Enums\AccountingOpenItemStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_ap_from_goods_receipt_and_record_supplier_payment(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $supplier = Supplier::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $po = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'rfq_id' => null,
            'status' => PurchaseOrderStatus::Confirmed,
            'order_date' => now()->toDateString(),
            'notes' => null,
        ]);

        $line = $po->lines()->create([
            'product_id' => $product->id,
            'quantity_ordered' => '10.0000',
            'unit_cost' => '2.5000',
            'position' => 0,
        ]);

        $receipt = app(PostGoodsReceiptService::class)->execute(
            $tenant->id,
            $po->id,
            [
                ['purchase_order_line_id' => $line->id, 'quantity_received' => '4'],
            ],
            now()->toDateTimeString(),
            'BILL-001',
            null,
        );

        $ap = app(PostAccountsPayableFromGoodsReceiptService::class)->execute($tenant->id, $receipt->id);

        $this->assertDatabaseHas('accounts_payable', [
            'id' => $ap->id,
            'tenant_id' => $tenant->id,
            'goods_receipt_id' => $receipt->id,
            'supplier_id' => $supplier->id,
            'total_amount' => '10.0000',
            'amount_paid' => '0.0000',
            'status' => AccountingOpenItemStatus::Open->value,
        ]);

        app(RecordSupplierPaymentService::class)->execute(
            $tenant->id,
            $supplier->id,
            '10.0000',
            now()->toDateTimeString(),
            'CHK-1',
            null,
            [
                ['accounts_payable_id' => $ap->id, 'amount' => '10.0000'],
            ],
        );

        $ap->refresh();
        $this->assertSame(AccountingOpenItemStatus::Paid, $ap->status);
        $this->assertSame('10.0000', (string) $ap->amount_paid);
    }
}
