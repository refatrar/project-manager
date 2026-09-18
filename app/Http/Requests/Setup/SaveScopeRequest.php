<?php

namespace App\Http\Requests\Setup;

use App\Enums\ScopeStatus;
use App\Models\Setup\Scope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveScopeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $scope = $this->route('scope');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('scopes', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($scope instanceof Scope ? $scope->id : null),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(ScopeStatus::class)],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('description') === '') {
            $this->merge(['description' => null]);
        }
    }
}
