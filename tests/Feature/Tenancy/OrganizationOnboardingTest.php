<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_organization_screen_renders_for_authenticated_user_without_tenant(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('organization.create'));
        $response->assertOk();
    }

    public function test_user_with_organization_is_redirected_away_from_create_organization(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'owner']);
        $this->actingAs($user);

        Livewire::test('pages::organization.create')
            ->assertRedirect(route('dashboard'));
    }

    public function test_user_can_create_organization_and_reach_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test('pages::organization.create')
            ->set('name', 'Cebu Hardware Corp.')
            ->call('createOrganization')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('tenants', [
            'name' => 'Cebu Hardware Corp.',
        ]);

        $this->assertDatabaseHas('tenant_user', [
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $this->assertNotNull(session('current_tenant_id'));
    }
}
