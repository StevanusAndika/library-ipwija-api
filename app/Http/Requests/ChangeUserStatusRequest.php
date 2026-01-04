<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeUserStatusRequest extends FormRequest
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
            'user_id' => 'required|integer|exists:users,id',
            'status' => 'required|in:PENDING,ACTIVE,SUSPENDED,INACTIVE',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'ID User dibutuhkan',
            'user_id.exists' => 'User tidak ditemukan',
            'status.required' => 'Status dibutuhkan',
            'status.in' => 'Status harus salah satu dari: PENDING, ACTIVE, SUSPENDED, INACTIVE',
        ];
    }
}
