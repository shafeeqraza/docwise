<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Document;
use App\Policies\DocumentPolicy;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentPolicyTest extends TestCase
{
    use PolicyTestHelpers;

    private DocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DocumentPolicy;
    }

    private function document(int $companyId = self::COMPANY, int $uploadedBy = 99): Document
    {
        return (new Document)->forceFill(['company_id' => $companyId, 'uploaded_by' => $uploadedBy]);
    }

    public function test_policy_is_registered_via_use_policy_attribute(): void
    {
        $this->assertInstanceOf(DocumentPolicy::class, Gate::getPolicyFor(Document::class));
    }

    /** @return array<string, array{UserRole}> */
    public static function allRoles(): array
    {
        return array_combine(
            array_map(fn (UserRole $role) => $role->value, UserRole::cases()),
            array_map(fn (UserRole $role) => [$role], UserRole::cases()),
        );
    }

    #[DataProvider('allRoles')]
    public function test_every_role_can_list_and_view_documents_in_its_company(UserRole $role): void
    {
        $user = $this->user($role);

        $this->assertAllowed($this->policy->viewAny($user));
        $this->assertAllowed($this->policy->view($user, $this->document()));
    }

    public function test_viewing_another_company_document_is_hidden_as_not_found(): void
    {
        $result = $this->policy->view($this->user(UserRole::ADMIN), $this->document(self::OTHER_COMPANY));

        $this->assertHiddenAsNotFound($result);
    }

    public function test_super_admin_can_view_any_company_document(): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN);

        $this->assertAllowed($this->policy->view($user, $this->document(self::OTHER_COMPANY)));
    }

    public function test_admins_and_agents_can_upload(): void
    {
        $this->assertAllowed($this->policy->create($this->user(UserRole::ADMIN)));
        $this->assertAllowed($this->policy->create($this->user(UserRole::AGENT)));
        $this->assertAllowed($this->policy->create($this->user(UserRole::SUPER_ADMIN)));
    }

    public function test_api_user_cannot_upload(): void
    {
        $this->assertForbidden($this->policy->create($this->user(UserRole::API_USER)));
    }

    public function test_admin_can_delete_any_document_in_its_company(): void
    {
        $admin = $this->user(UserRole::ADMIN, id: 10);

        $this->assertAllowed($this->policy->delete($admin, $this->document(uploadedBy: 99)));
    }

    public function test_agent_can_delete_its_own_upload(): void
    {
        $agent = $this->user(UserRole::AGENT, id: 10);

        $this->assertAllowed($this->policy->delete($agent, $this->document(uploadedBy: 10)));
    }

    public function test_agent_cannot_delete_another_users_upload(): void
    {
        $agent = $this->user(UserRole::AGENT, id: 10);

        $this->assertForbidden($this->policy->delete($agent, $this->document(uploadedBy: 99)));
    }

    public function test_api_user_cannot_delete_another_users_upload(): void
    {
        $apiUser = $this->user(UserRole::API_USER, id: 10);

        $this->assertForbidden($this->policy->delete($apiUser, $this->document(uploadedBy: 99)));
    }

    public function test_deleting_another_company_document_is_hidden_as_not_found(): void
    {
        $admin = $this->user(UserRole::ADMIN, id: 10);

        $this->assertHiddenAsNotFound(
            $this->policy->delete($admin, $this->document(self::OTHER_COMPANY, uploadedBy: 10))
        );
    }

    public function test_super_admin_can_delete_any_company_document(): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN);

        $this->assertAllowed($this->policy->delete($user, $this->document(self::OTHER_COMPANY)));
    }
}
