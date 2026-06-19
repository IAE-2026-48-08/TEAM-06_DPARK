<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimVoucherRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'member_id' => 'required|integer|exists:members,id',
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'ID member wajib diisi.',
            'member_id.exists'   => 'Member tidak ditemukan.',
        ];
    }
}
