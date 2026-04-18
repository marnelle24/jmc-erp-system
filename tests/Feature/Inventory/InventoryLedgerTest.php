<?php

namespace Tests\Feature\Inventory;

use App\Domains\Inventory\Services\ExportInventoryMovementsCsvService;
use App\Domains\Inventory\Services\PostInventoryMovementService;
use App\Enums\InventoryMovementType;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_record_inventory_adjustment_via_livewire(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        Livewire::test('pages::inventory.adjustments.create')
            ->set('product_id', (string) $product->id)
            ->set('quantity', '12.5')
            ->set('notes', 'Cycle count')
            ->call('postAdjustment')
            ->assertRedirect(route('inventory.movements.index'));

        $this->assertDatabaseHas('inventory_movements', [
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'movement_type' => InventoryMovementType::Adjustment->value,
        ]);

        $sum = (float) $product->inventoryMovements()->sum('quantity');
        $this->assertEquals(12.5, $sum);
    }

    public function test_inventory_movements_page_renders_for_tenant_user(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $response = $this->get(route('inventory.movements.index'));
        $response->assertOk();
    }

    public function test_inventory_movements_livewire_filters_by_movement_type(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $productReceipt = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Receipt-only product']);
        $productAdjustment = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Adjustment-only product']);

        $post = app(PostInventoryMovementService::class);
        $post->execute($tenant->id, $productReceipt->id, '1', InventoryMovementType::Receipt, 'r');
        $post->execute($tenant->id, $productAdjustment->id, '2', InventoryMovementType::Adjustment, 'a');

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        Livewire::test('pages::inventory.movements.index')
            ->set('movement_type', InventoryMovementType::Adjustment->value)
            ->assertSee('Adjustment-only product')
            ->assertDontSee('Receipt-only product');
    }

    public function test_export_inventory_movements_csv_includes_headers_and_row(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Export SKU test',
            'sku' => 'SKU-EXPORT-1',
        ]);

        app(PostInventoryMovementService::class)->execute(
            $tenant->id,
            $product->id,
            '3.5',
            InventoryMovementType::Adjustment,
            'Line note',
        );

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $response = app(ExportInventoryMovementsCsvService::class)->download($tenant->id, []);

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('SKU', $csv);
        $this->assertStringContainsString('Source URL', $csv);
        $this->assertStringContainsString('SKU-EXPORT-1', $csv);
        $this->assertStringContainsString('Export SKU test', $csv);
        $this->assertStringContainsString('adjustment', $csv);
        $this->assertStringContainsString('Line note', $csv);
    }

    public function test_livewire_export_csv_action_returns_streamed_response(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        Livewire::test('pages::inventory.movements.index')
            ->call('exportCsv')
            ->assertOk();
    }
}
