<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyMemberRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'vehicle_plate' => 'required|string|max:20',
            'subtotal'      => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_plate.required' => 'Plat nomor kendaraan wajib diisi.',
            'subtotal.numeric'       => 'Subtotal harus berupa angka.',
        ];
    }
}
