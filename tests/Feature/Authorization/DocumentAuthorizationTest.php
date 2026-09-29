<?php

namespace Tests\Feature\Authorization;

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use App\Services\V1\Document\Upload\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $uploader;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        // DocumentService deletes the Cloudinary file; keep tests off the network.
        $this->mock(FileStorageService::class)
            ->shouldReceive('deleteFile')
            ->andReturnTrue();

        $this->company = Company::factory()->create();
        $this->uploader = User::factory()->agent()->create(['company_id' => $this->company->id]);
        $this->document = Document::factory()->uploadedBy($this->uploader)->create();
    }

    private function deleteDocument(User $user)
    {
        Sanctum::actingAs($user);

        return $this->deleteJson('/api/documents/'.$this->document->uuid);
    }

    public function test_uploader_agent_can_delete_own_document(): void
    {
        $this->deleteDocument($this->uploader)->assertOk();

        $this->assertSoftDeleted($this->document);
    }

    public function test_agent_cannot_delete_another_users_document(): void
    {
        $otherAgent = User::factory()->agent()->create(['company_id' => $this->company->id]);

        $this->deleteDocument($otherAgent)
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertNotSoftDeleted($this->document);
    }

    public function test_api_user_cannot_delete_documents(): void
    {
        $apiUser = User::factory()->apiUser()->create(['company_id' => $this->company->id]);

        $this->deleteDocument($apiUser)->assertForbidden();

        $this->assertNotSoftDeleted($this->document);
    }

    public function test_admin_can_delete_any_document_in_company(): void
    {
        $admin = User::factory()->admin()->create(['company_id' => $this->company->id]);

        $this->deleteDocument($admin)->assertOk();

        $this->assertSoftDeleted($this->document);
    }

    public function test_another_company_document_is_not_found(): void
    {
        $outsider = User::factory()->admin()->create();

        $this->deleteDocument($outsider)->assertNotFound();

        $this->assertNotSoftDeleted($this->document);
    }

    public function test_any_member_can_view_a_document(): void
    {
        $apiUser = User::factory()->apiUser()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($apiUser);

        $this->getJson('/api/documents/'.$this->document->uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', (string) $this->document->uuid);
    }

    public function test_api_user_cannot_upload(): void
    {
        $apiUser = User::factory()->apiUser()->create(['company_id' => $this->company->id]);
        Sanctum::actingAs($apiUser);

        $this->postJson('/api/documents', [
            'file' => UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $this->assertDatabaseCount('documents', 1);
    }
}
