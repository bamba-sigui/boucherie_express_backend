<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullify = [];
        foreach (['name', 'phone', 'email'] as $field) {
            if ($this->input($field) === '') {
                $nullify[$field] = null;
            }
        }
        if ($nullify) {
            $this->merge($nullify);
        }
    }

    public function rules(): array
    {
        return [
            'name'  => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:30',
            'email' => 'sometimes|nullable|email|unique:users,email,' . $this->user()->id,
        ];
    }
}
