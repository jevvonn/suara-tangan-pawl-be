<?php

namespace App\Http\Requests\FavoriteSentence;

use Illuminate\Foundation\Http\FormRequest;

class StoreFavoriteSentenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // any logged-in user (route uses auth:sanctum)
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sentence' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Collapse repeated whitespace, so "halo   apa" and "halo apa" are the same favorite.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('sentence'))) {
            $this->merge(['sentence' => trim((string) preg_replace('/\s+/u', ' ', $this->input('sentence')))]);
        }
    }
}
