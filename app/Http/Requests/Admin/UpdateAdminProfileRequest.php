<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminProfileRequest extends FormRequest
{
    /**
     * Any signed-in platform admin may edit their own name and email.
     * Role assignment is a different permission (`admins.manage`) and is
     * not part of this request.
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
        /** @var Admin $admin */
        $admin = $this->user('admin');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(Admin::class, 'email')->ignore($admin->id),
            ],
        ];
    }
}
