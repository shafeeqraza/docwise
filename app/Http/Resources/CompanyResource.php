<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'subscription_plan' => $this->subscription_plan,
            'billing_cycle' => $this->billing_cycle,
            'next_billing_date' => $this->next_billing_date?->toIso8601String(),
            'payment_status' => $this->payment_status,
            'allow_overages' => $this->allow_overages,
            'settings' => $this->settings,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
