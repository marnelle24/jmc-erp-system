<?php

namespace Tests\Feature\Procurement;

use App\Enums\RfqLineUnitType;
use App\Enums\RfqStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RfqLine;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseOrderCreatePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_purchase_order_from_rf_q_template_and_closes_rf_q(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $supplier = Supplier::factory()->create(['tenant_id' => $tenant->id]);
        $p1 = Product::factory()->create(['tenant_id' => $tenant->id]);
        $p2 = Product::factory()->create(['tenant_id' => $tenant->id]);

        $code = 'RFQ'.strtoupper(uniqid());
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'supplier_id' => $supplier->id,
            'reference_code' => $code,
            'status' => RfqStatus::ApprovedNoPo,
            'title' => 'Office supplies',
            'notes' => 'Q1 restock',
            'created_by' => $user->id,
        ]);

        RfqLine::query()->create([
            'rfq_id' => $rfq->id,
            'product_id' => $p1->id,
            'quantity' => '5.0000',
            'unit_type' => RfqLineUnitType::Piece->value,
            'unit_price' => '10.0000',
            'notes' => null,
        ]);
        RfqLine::query()->create([
            'rfq_id' => $rfq->id,
            'product_id' => $p2->id,
            'quantity' => '2.0000',
            'unit_type' => RfqLineUnitType::Piece->value,
            'unit_price' => '4.5000',
            'notes' => null,
        ]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        Livewire::test('pages::procurement.purchase-orders.create')
            ->set('rfq_id', (string) $rfq->id)
            ->assertSet('supplier_id', (string) $supplier->id)
            ->set('order_date', now()->toDateString())
            ->set('lines.0.quantity_ordered', '6')
            ->set('lines.1.quantity_ordered', '2')
            ->call('save')
            ->assertRedirect();

        $po = PurchaseOrder::query()->where('tenant_id', $tenant->id)->latest('id')->first();
        $this->assertNotNull($po);
        $this->assertSame($rfq->id, (int) $po->rfq_id);
        $this->assertCount(2, $po->lines);
        $rfq->refresh();
        $this->assertSame(RfqStatus::Closed, $rfq->status);
    }

    public function test_creates_manual_purchase_order_without_rf_q(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $supplier = Supplier::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        Livewire::test('pages::procurement.purchase-orders.create')
            ->set('supplier_id', (string) $supplier->id)
            ->set('order_date', now()->toDateString())
            ->set('lines.0.product_id', (string) $product->id)
            ->set('lines.0.quantity_ordered', '3')
            ->set('lines.0.unit_cost', '12.50')
            ->call('save')
            ->assertRedirect();

        $po = PurchaseOrder::query()->where('tenant_id', $tenant->id)->latest('id')->first();
        $this->assertNotNull($po);
        $this->assertNull($po->rfq_id);
        $this->assertCount(1, $po->lines);
        $this->assertSame('3.0000', $po->lines->first()->quantity_ordered);
    }
}
