<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\CompanyApiKey;
use App\Policies\CompanyApiKeyPolicy;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyApiKeyPolicyTest extends TestCase
{
    use PolicyTestHelpers;

    private CompanyApiKeyPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new CompanyApiKeyPolicy;
    }

    private function apiKey(int $companyId = self::COMPANY): CompanyApiKey
    {
        return (new CompanyApiKey)->forceFill(['company_id' => $companyId]);
    }

    public function test_policy_is_registered_via_use_policy_attribute(): void
    {
        $this->assertInstanceOf(CompanyApiKeyPolicy::class, Gate::getPolicyFor(CompanyApiKey::class));
    }

    /** @return array<string, array{string}> */
    public static function recordAbilities(): array
    {
        return [
            'view' => ['view'],
            'update' => ['update'],
            'delete' => ['delete'],
            'regenerate' => ['regenerate'],
        ];
    }

    public function test_admin_can_list_and_create_keys(): void
    {
        $admin = $this->user(UserRole::ADMIN);

        $this->assertAllowed($this->policy->viewAny($admin));
        $this->assertAllowed($this->policy->create($admin));
    }

    public function test_non_admins_cannot_list_or_create_keys(): void
    {
        foreach ([UserRole::AGENT, UserRole::API_USER] as $role) {
            $this->assertForbidden($this->policy->viewAny($this->user($role)));
            $this->assertForbidden($this->policy->create($this->user($role)));
        }
    }

    #[DataProvider('recordAbilities')]
    public function test_admin_can_manage_own_company_key(string $ability): void
    {
        $this->assertAllowed($this->policy->{$ability}($this->user(UserRole::ADMIN), $this->apiKey()));
    }

    #[DataProvider('recordAbilities')]
    public function test_agent_cannot_manage_own_company_key(string $ability): void
    {
        $this->assertForbidden($this->policy->{$ability}($this->user(UserRole::AGENT), $this->apiKey()));
    }

    #[DataProvider('recordAbilities')]
    public function test_another_company_key_is_hidden_as_not_found(string $ability): void
    {
        $this->assertHiddenAsNotFound(
            $this->policy->{$ability}($this->user(UserRole::ADMIN), $this->apiKey(self::OTHER_COMPANY))
        );
    }

    #[DataProvider('recordAbilities')]
    public function test_super_admin_can_manage_any_company_key(string $ability): void
    {
        $this->assertAllowed(
            $this->policy->{$ability}($this->user(UserRole::SUPER_ADMIN), $this->apiKey(self::OTHER_COMPANY))
        );
    }
}
