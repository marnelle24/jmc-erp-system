<?php

namespace Tests\Feature\Inventory;

use App\Domains\Inventory\Services\GetProductStockKpisService;
use App\Domains\Inventory\Services\PostInventoryMovementService;
use App\Enums\InventoryMovementType;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Product360Test extends TestCase
{
    use RefreshDatabase;

    public function test_product_show_returns_404_for_other_tenant_product(): void
    {
        $user = User::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user->tenants()->attach($tenantA, ['role' => 'owner']);

        $productB = Product::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenantA->id]);

        $this->get(route('products.show', $productB->id))->assertNotFound();
    }

    public function test_product_show_renders_for_tenant_user(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Widget A',
        ]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $this->get(route('products.show', $product->id))
            ->assertOk()
            ->assertSee('Widget A');
    }

    public function test_stock_kpis_reflect_on_hand_from_movements(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $post = app(PostInventoryMovementService::class);
        $post->execute($tenant->id, $product->id, '5', InventoryMovementType::Adjustment, 'count');

        $kpis = app(GetProductStockKpisService::class)->execute($tenant->id, $product->id);

        $this->assertSame('5', $kpis->onHand);
    }

    public function test_user_can_save_reorder_settings_on_product_show(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        Livewire::test('pages::products.show', ['id' => $product->id])
            ->set('reorder_point_input', '10')
            ->set('reorder_qty_input', '25')
            ->call('saveReorder')
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertSame('10.0000', (string) $product->reorder_point);
        $this->assertSame('25.0000', (string) $product->reorder_qty);
    }

    public function test_low_stock_callout_visible_when_on_hand_at_or_below_reorder_point(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'reorder_point' => '10',
        ]);

        $post = app(PostInventoryMovementService::class);
        $post->execute($tenant->id, $product->id, '5', InventoryMovementType::Adjustment, 'x');

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        $this->get(route('products.show', $product->id))
            ->assertOk()
            ->assertSee(__('Below reorder level'));
    }
}
