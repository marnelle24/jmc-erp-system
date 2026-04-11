<?php

namespace Tests\Feature\Sales;

use App\Domains\Inventory\Services\PostInventoryMovementService;
use App\Domains\Sales\Services\CreateSalesOrderService;
use App\Domains\Sales\Services\IssueSalesInvoiceService;
use App\Domains\Sales\Services\PostSalesShipmentService;
use App\Enums\InventoryMovementType;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesShipmentStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoiceLine;
use App\Models\SalesShipmentLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_creates_issue_movements_and_invoice_respects_shipped_quantities(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $move = app(PostInventoryMovementService::class);
        $move->execute(
            $tenant->id,
            $product->id,
            '100',
            InventoryMovementType::Adjustment,
            'Test stock',
        );

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $order = app(CreateSalesOrderService::class)->execute($tenant->id, [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'notes' => null,
            'lines' => [
                ['product_id' => $product->id, 'quantity_ordered' => '10', 'unit_price' => '25'],
            ],
        ]);

        $line = $order->lines()->firstOrFail();

        $shipment = app(PostSalesShipmentService::class)->execute(
            $tenant->id,
            $order->id,
            [
                ['sales_order_line_id' => $line->id, 'quantity_shipped' => '4'],
            ],
            now()->toDateTimeString(),
            null,
        );

        $this->assertSame(SalesShipmentStatus::Posted, $shipment->status);

        $shipLine = SalesShipmentLine::query()->where('sales_shipment_id', $shipment->id)->firstOrFail();

        $this->assertDatabaseHas('inventory_movements', [
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'movement_type' => InventoryMovementType::Issue->value,
            'quantity' => '-4.0000',
            'reference_type' => SalesShipmentLine::class,
            'reference_id' => $shipLine->id,
        ]);

        $order->refresh();
        $this->assertSame(SalesOrderStatus::PartiallyFulfilled, $order->status);

        app(PostSalesShipmentService::class)->execute(
            $tenant->id,
            $order->id,
            [
                ['sales_order_line_id' => $line->id, 'quantity_shipped' => '6'],
            ],
            now()->toDateTimeString(),
            null,
        );

        $order->refresh();
        $this->assertSame(SalesOrderStatus::Fulfilled, $order->status);

        $invoice = app(IssueSalesInvoiceService::class)->execute(
            $tenant->id,
            $order->id,
            [
                ['sales_order_line_id' => $line->id, 'quantity_invoiced' => '10', 'unit_price' => '25'],
            ],
            now()->toDateTimeString(),
            'CUST-PO-1',
            null,
        );

        $this->assertSame(SalesInvoiceStatus::Issued, $invoice->status);
        $this->assertDatabaseHas('sales_invoices', [
            'id' => $invoice->id,
            'tenant_id' => $tenant->id,
            'sales_order_id' => $order->id,
            'customer_document_reference' => 'CUST-PO-1',
        ]);

        $invLine = SalesInvoiceLine::query()->where('sales_invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('10.0000', (string) $invLine->quantity_invoiced);
    }

    public function test_cannot_invoice_more_than_shipped(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        app(PostInventoryMovementService::class)->execute(
            $tenant->id,
            $product->id,
            '10',
            InventoryMovementType::Adjustment,
            null,
        );

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $order = app(CreateSalesOrderService::class)->execute($tenant->id, [
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'lines' => [
                ['product_id' => $product->id, 'quantity_ordered' => '10', 'unit_price' => null],
            ],
        ]);

        $line = $order->lines()->firstOrFail();

        app(PostSalesShipmentService::class)->execute(
            $tenant->id,
            $order->id,
            [
                ['sales_order_line_id' => $line->id, 'quantity_shipped' => '3'],
            ],
            now()->toDateTimeString(),
            null,
        );

        $this->expectException(\InvalidArgumentException::class);
        app(IssueSalesInvoiceService::class)->execute(
            $tenant->id,
            $order->id,
            [
                ['sales_order_line_id' => $line->id, 'quantity_invoiced' => '5'],
            ],
            now()->toDateTimeString(),
            null,
            null,
        );
    }
}
