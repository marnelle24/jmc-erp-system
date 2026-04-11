<?php

namespace Tests\Feature\Inventory;

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
}
