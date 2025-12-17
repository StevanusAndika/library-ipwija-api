<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $this->user()->id,
            'role' => 'nullable|in:admin,user',
            'nim' => 'nullable|string|unique:users,nim,' . $this->user()->id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'tempat_lahir' => 'nullable|string|max:255',
            'tanggal_lahir' => 'nullable|date',
            'agama' => 'nullable|in:ISLAM,KRISTEN,HINDU,BUDDHA,KATOLIK,KONGHUCU',
            'status' => 'nullable|in:PENDING,ACTIVE,SUSPENDED,INACTIVE',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }
}
