<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchInsertUsersRequest extends FormRequest
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
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // Max 10MB
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'csv_file.required' => 'File CSV harus diupload',
            'csv_file.file' => 'File harus berupa file',
            'csv_file.mimes' => 'File harus berupa CSV atau TXT',
            'csv_file.max' => 'Ukuran file maksimal 10MB',
        ];
    }
}
