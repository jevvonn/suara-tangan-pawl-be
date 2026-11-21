<?php

namespace App\Http\Requests\EducationNews;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Used for both create (POST /education-news) and update (PUT /education-news/{news}).
 *
 * Thumbnail on update:
 *   - send `thumbnail` (file)      -> replace it
 *   - send `remove_thumbnail=1`    -> remove it (frontend shows the default thumbnail)
 *   - send neither                 -> keep the current one
 */
class EducationNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // access is checked by the `admin` middleware on the route
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            // `content` is a MySQL TEXT column (65,535 bytes); 16,000 chars fits even with 4-byte characters.
            'content' => ['required', 'string', 'max:16000'],
            'thumbnail' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // KB, same 2 MB limit as the dashboard's image picker
            ],
            'remove_thumbnail' => ['sometimes', 'boolean', 'prohibits:thumbnail'],
        ];
    }
}
