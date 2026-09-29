<?php

namespace Database\Factories;

use App\Enums\DocumentFileType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state. The uuid is filled by DocumentObserver.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'uploaded_by' => fn (array $attributes) => User::factory()->create([
                'company_id' => $attributes['company_id'],
            ])->id,
            'title' => fake()->sentence(3),
            'file_type' => DocumentFileType::PDF,
            'public_id' => 'docwise/'.fake()->uuid(),
        ];
    }

    /**
     * Attribute the document to an existing user in that user's company.
     */
    public function uploadedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $user->company_id,
            'uploaded_by' => $user->id,
        ]);
    }
}
