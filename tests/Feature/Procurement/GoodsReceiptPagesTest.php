<?php

namespace Tests\Feature\Procurement;

use App\Domains\Procurement\Services\PostGoodsReceiptService;
use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoodsReceiptPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_goods_receipt_register_and_detail_are_reachable_for_tenant_user(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $supplier = Supplier::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $po = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'reference_code' => 'PO-GR-PAGE-1',
            'rfq_id' => null,
            'status' => PurchaseOrderStatus::Confirmed,
            'order_date' => now()->toDateString(),
            'notes' => null,
        ]);

        $line = $po->lines()->create([
            'product_id' => $product->id,
            'quantity_ordered' => '5.0000',
            'unit_cost' => '2.0000',
            'position' => 0,
        ]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $receipt = app(PostGoodsReceiptService::class)->execute(
            $tenant->id,
            $po->id,
            [['purchase_order_line_id' => $line->id, 'quantity_received' => '3']],
            now()->toDateTimeString(),
            'INV-GR-TEST',
            'Dock A',
        );

        $this->get(route('procurement.goods-receipts.index'))
            ->assertOk()
            ->assertSee('PO-GR-PAGE-1', false);

        $this->get(route('procurement.goods-receipts.show', $receipt->id))
            ->assertOk()
            ->assertSee('Dock A', false)
            ->assertSee('INV-GR-TEST', false);

        Livewire::test('pages::procurement.goods-receipts.index')
            ->assertSet('supplierFilter', '')
            ->set('supplierFilter', (string) $supplier->id)
            ->assertSee('PO-GR-PAGE-1', false);

        Livewire::test('pages::procurement.goods-receipts.index')
            ->set('poSearch', 'GR-PAGE')
            ->assertSee('PO-GR-PAGE-1', false);
    }
}
