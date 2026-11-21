<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Admins can only change a user's role; name, email and password belong to the user.
 */
class UpdateUserRoleRequest extends FormRequest
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
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    /**
     * An admin can't change their own role, so there is always at least one admin left.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->route('user')?->is($this->user())) {
                    $validator->errors()->add('role', 'You cannot change your own role.');
                }
            },
        ];
    }
}
