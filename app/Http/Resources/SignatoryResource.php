<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignatoryResource extends JsonResource
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
            'name' => $this->name,
            'initials' => $this->initials,
            'designation' => $this->designation,
            'office' => $this->office,
            'minimum_amount' => $this->minimum_amount,
            'maximum_amount' => $this->maximum_amount,
            'is_active' => $this->is_active,
            'has_p12_certificate' => $this->p12_path !== null,
            'has_signature_image' => $this->signature_image_path !== null,
            'has_pnpki_password' => $this->getRawOriginal('pnpki_password') !== null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
