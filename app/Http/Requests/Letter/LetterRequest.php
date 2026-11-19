<?php

namespace App\Http\Requests\Letter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Used for both create (POST /letters) and update (PUT /letters/{letter}).
 * The image is required on create and optional on update (keeps the current one).
 */
class LetterRequest extends FormRequest
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
        $letter = $this->route('letter');

        return [
            'symbol' => [
                'required',
                'string',
                'max:3',
                Rule::unique('letters', 'symbol')->ignore($letter),
            ],
            'image' => [
                $letter ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // KB, same 2 MB limit as the dashboard's image picker
            ],
        ];
    }

    /**
     * Normalize the symbol before validating so the unique check compares "a" against "A".
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('symbol'))) {
            $this->merge(['symbol' => mb_strtoupper(trim($this->input('symbol')))]);
        }
    }
}
