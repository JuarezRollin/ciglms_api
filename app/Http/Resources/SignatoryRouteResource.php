<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignatoryRouteResource extends JsonResource
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
            'guarantee_letter_id' => $this->guarantee_letter_id,
            'signatory_id' => $this->signatory_id,
            'sequence' => $this->sequence,
            'date_sent' => $this->date_sent,
            'date_returned' => $this->date_returned,
            'remarks' => $this->remarks,
            'signatory' => SignatoryResource::make($this->whenLoaded('signatory')),
        ];
    }
}
