<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidateVoucherRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'voucher_code'   => 'required|string|exists:vouchers,code',
            'member_id'      => 'required|integer|exists:members,id',
            'subtotal'       => 'required|numeric|min:0',
            'duration_hours' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'voucher_code.required'   => 'Kode voucher wajib diisi.',
            'voucher_code.exists'     => 'Kode voucher tidak ditemukan.',
            'member_id.required'      => 'ID member wajib diisi.',
            'member_id.exists'        => 'Member tidak ditemukan.',
            'subtotal.required'       => 'Subtotal biaya parkir wajib diisi.',
            'duration_hours.required' => 'Durasi parkir (jam) wajib diisi.',
        ];
    }
}
