<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateAdminPasswordRequest extends FormRequest
{
    /**
     * Any signed-in platform admin may change their own password.
     * The current password is checked against the `admin` guard, not the
     * default web guard.
     */
    public function authorize(): bool
    {
        return $this->user('admin') instanceof Admin;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:admin'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }
}
