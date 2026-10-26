<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuaranteeLetterDocumentResource extends JsonResource
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
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'uploaded_at' => $this->created_at,
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
        ];
    }
}
