<?php

namespace Database\Factories;

use App\Models\GuaranteeLetterSupportingDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuaranteeLetterSupportingDocument>
 */
class GuaranteeLetterSupportingDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disk' => 'local',
            'path' => 'guarantee-letters/documents/'.fake()->uuid().'.png',
            'original_name' => 'receipt.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ];
    }
}
