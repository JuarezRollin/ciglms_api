<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceProviderResource extends JsonResource
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
            'region_id' => $this->region_id,
            'name' => $this->name,
            'status' => $this->status,
            'address' => $this->address,
            'salutation' => $this->salutation,
            'addressee' => $this->addressee,
            'designation' => $this->designation,
            'category' => $this->category,
            'sp_remarks' => $this->sp_remarks,
            'contact_number' => $this->contact_number,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'business_name' => $this->business_name,
            'moa' => $this->moa,
            'jurisdiction' => $this->jurisdiction,
            'is_active' => $this->is_active,
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
