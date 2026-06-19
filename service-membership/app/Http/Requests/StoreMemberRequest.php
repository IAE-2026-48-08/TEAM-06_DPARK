<?php

namespace App\Http\Requests;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:members,email',
            'phone'           => 'required|string|max:20',
            'vehicle_plate'   => 'required|string|max:20',
            'membership_type' => ['required', Rule::in(['reguler', 'premium', 'vip'])],
            'expired_at'      => 'nullable|date|after:today',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'           => 'Email sudah terdaftar sebagai member.',
            'membership_type.in'     => 'Tipe membership harus salah satu dari: reguler, premium, vip.',
            'expired_at.after'       => 'Tanggal kadaluarsa harus setelah hari ini.',
        ];
    }
}
