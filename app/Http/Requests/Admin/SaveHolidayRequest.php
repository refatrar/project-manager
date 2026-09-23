<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminPermission;
use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $admin = $this->user('admin');

        return $admin instanceof Admin
            && $admin->hasPermission(AdminPermission::ManageWorkSchedules->value);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date', Rule::unique('holidays', 'date')],
        ];
    }
}
