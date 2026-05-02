<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'  => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:30',
            'email' => 'sometimes|email|unique:users,email,' . $this->user()->id,
        ];
    }
}
