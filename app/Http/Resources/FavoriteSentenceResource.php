<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\FavoriteSentence
 */
class FavoriteSentenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sentence' => $this->sentence,
            // Letters found for this sentence (same shape as GET /letters/translate);
            // the frontend shows an empty card for symbols that have no letter yet.
            'letters' => LetterResource::collection($this->whenLoaded('letters')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
