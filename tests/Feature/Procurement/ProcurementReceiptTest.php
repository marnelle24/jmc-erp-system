<?php

namespace Tests\Feature\Procurement;

use App\Domains\Procurement\Services\PostGoodsReceiptService;
use App\Enums\GoodsReceiptStatus;
use App\Enums\InventoryMovementType;
use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceiptLine;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_goods_receipt_creates_inventory_movements_and_updates_po_status(): void
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
            'unit_cost' => '1.0000',
            'position' => 0,
        ]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $service = app(PostGoodsReceiptService::class);
        $receipt = $service->execute(
            $tenant->id,
            $po->id,
            [
                ['purchase_order_line_id' => $line->id, 'quantity_received' => '4'],
            ],
            now()->toDateTimeString(),
            'SUP-INV-99',
            null,
        );

        $this->assertDatabaseHas('goods_receipts', [
            'id' => $receipt->id,
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'status' => GoodsReceiptStatus::Posted->value,
            'supplier_invoice_reference' => 'SUP-INV-99',
        ]);

        $grLine = GoodsReceiptLine::query()->where('goods_receipt_id', $receipt->id)->firstOrFail();

        $this->assertDatabaseHas('inventory_movements', [
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'movement_type' => InventoryMovementType::Receipt->value,
            'reference_type' => GoodsReceiptLine::class,
            'reference_id' => $grLine->id,
        ]);

        $po->refresh();
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $po->status);

        $service->execute(
            $tenant->id,
            $po->id,
            [
                ['purchase_order_line_id' => $line->id, 'quantity_received' => '6'],
            ],
            now()->toDateTimeString(),
            null,
            null,
        );

        $po->refresh();
        $this->assertSame(PurchaseOrderStatus::Received, $po->status);
    }
}
