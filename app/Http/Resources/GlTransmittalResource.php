<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GlTransmittalResource extends JsonResource
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
            'bundle_no' => $this->bundle_no,
            'category_id' => $this->category_id,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn (): array => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'initials' => $this->creator->initials,
            ]),
            'received_by' => $this->received_by,
            'receiver' => $this->whenLoaded('receiver', fn (): ?array => $this->receiver === null ? null : [
                'id' => $this->receiver->id,
                'name' => $this->receiver->name,
            ]),
            'gls' => GuaranteeLetterResource::collection($this->whenLoaded('gls')),
            'gl_count' => $this->whenCounted('gls'),
            'received_gl_count' => $this->relationLoaded('gls')
                ? $this->gls->whereNotNull('date_received_gl')->count()
                : (int) ($this->received_gl_count ?? 0),
            'cancelled_at' => $this->cancelled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
