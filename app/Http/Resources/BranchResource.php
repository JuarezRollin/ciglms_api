<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
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
            'service_provider_id' => $this->service_provider_id,
            'name' => $this->name,
            'address' => $this->address,
            'branch_salutation' => $this->branch_salutation,
            'branch_addressee' => $this->branch_addressee,
            'branch_designation' => $this->branch_designation,
            'is_active' => $this->is_active,
            'service_provider' => ServiceProviderResource::make($this->whenLoaded('serviceProvider')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
