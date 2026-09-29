<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\ChatSession;
use App\Policies\ChatSessionPolicy;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ChatSessionPolicyTest extends TestCase
{
    use PolicyTestHelpers;

    private ChatSessionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ChatSessionPolicy;
    }

    private function chatSession(int $companyId = self::COMPANY): ChatSession
    {
        return (new ChatSession)->forceFill(['company_id' => $companyId]);
    }

    public function test_policy_is_registered_via_use_policy_attribute(): void
    {
        $this->assertInstanceOf(ChatSessionPolicy::class, Gate::getPolicyFor(ChatSession::class));
    }

    public function test_company_members_can_view_sessions(): void
    {
        foreach ([UserRole::ADMIN, UserRole::AGENT, UserRole::API_USER] as $role) {
            $this->assertAllowed($this->policy->viewAny($this->user($role)));
            $this->assertAllowed($this->policy->view($this->user($role), $this->chatSession()));
        }
    }

    public function test_another_company_session_is_hidden_as_not_found(): void
    {
        $admin = $this->user(UserRole::ADMIN);

        $this->assertHiddenAsNotFound($this->policy->view($admin, $this->chatSession(self::OTHER_COMPANY)));
        $this->assertHiddenAsNotFound($this->policy->delete($admin, $this->chatSession(self::OTHER_COMPANY)));
    }

    public function test_only_admins_can_delete_sessions(): void
    {
        $this->assertAllowed($this->policy->delete($this->user(UserRole::ADMIN), $this->chatSession()));
        $this->assertForbidden($this->policy->delete($this->user(UserRole::AGENT), $this->chatSession()));
        $this->assertForbidden($this->policy->delete($this->user(UserRole::API_USER), $this->chatSession()));
    }

    public function test_super_admin_can_view_and_delete_any_company_session(): void
    {
        $user = $this->user(UserRole::SUPER_ADMIN);

        $this->assertAllowed($this->policy->view($user, $this->chatSession(self::OTHER_COMPANY)));
        $this->assertAllowed($this->policy->delete($user, $this->chatSession(self::OTHER_COMPANY)));
    }
}
