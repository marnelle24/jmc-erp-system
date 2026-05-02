<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_product_with_multiple_categories_via_livewire(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);

        $a = ProductCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Alpha']);
        $b = ProductCategory::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Beta']);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        Livewire::test('pages::products.index')
            ->set('name', 'Grouped item')
            ->set('sku', 'G-001')
            ->set('category_ids', [$a->id, $b->id])
            ->set('new_categories_input', 'Gamma')
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::query()->where('tenant_id', $tenant->id)->where('sku', 'G-001')->first();
        $this->assertNotNull($product);
        $this->assertCount(3, $product->categories);
        $this->assertTrue($product->categories->pluck('name')->contains('Gamma'));
    }

    public function test_cannot_assign_category_from_other_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);

        $foreign = ProductCategory::factory()->create(['tenant_id' => $other->id, 'name' => 'Foreign']);

        $this->actingAs($user);
        session(['current_tenant_id' => $tenant->id]);

        Livewire::test('pages::products.index')
            ->set('name', 'Bad cat')
            ->set('category_ids', [$foreign->id])
            ->call('saveProduct')
            ->assertHasErrors(['category_ids.0']);
    }
}
