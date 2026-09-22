<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `mimes:` instead of the bare `image` rule deliberately
            // excludes SVG — an uploaded SVG can carry an embedded
            // <script>, and this file is served back from the app's own
            // origin, which is a real stored-XSS path if visited directly
            // (not through the safely-rasterizing <img> tag every current
            // avatar display uses).
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ];
    }
}
