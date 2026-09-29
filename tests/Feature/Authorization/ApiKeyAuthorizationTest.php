<?php

namespace Tests\Feature\Authorization;

use App\Models\Company;
use App\Models\CompanyApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiKeyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private CompanyApiKey $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->admin()->create(['company_id' => $this->company->id]);
        $this->apiKey = CompanyApiKey::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_view_update_and_regenerate_own_company_key(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson("/api/api-keys/{$this->apiKey->uuid}")->assertOk();

        $this->putJson("/api/api-keys/{$this->apiKey->uuid}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->postJson("/api/api-keys/{$this->apiKey->uuid}/regenerate")->assertOk();
    }

    public function test_admin_can_revoke_own_company_key(): void
    {
        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/api-keys/{$this->apiKey->uuid}")->assertOk();

        $this->assertModelMissing($this->apiKey);
    }

    public function test_another_company_key_is_not_found(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson("/api/api-keys/{$this->apiKey->uuid}")->assertNotFound();
        $this->deleteJson("/api/api-keys/{$this->apiKey->uuid}")->assertNotFound();

        $this->assertModelExists($this->apiKey);
    }

    public function test_agent_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->agent()->create(['company_id' => $this->company->id]));

        $this->getJson('/api/api-keys')->assertForbidden();
        $this->deleteJson("/api/api-keys/{$this->apiKey->uuid}")->assertForbidden();

        $this->assertModelExists($this->apiKey);
    }

    public function test_super_admin_manages_keys_of_the_impersonated_company(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($superAdmin);
        $headers = ['X-Company-Id' => (string) $this->company->id];

        $this->getJson('/api/api-keys', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.uuid', (string) $this->apiKey->uuid);

        $this->getJson("/api/api-keys/{$this->apiKey->uuid}", $headers)->assertOk();
    }
}
