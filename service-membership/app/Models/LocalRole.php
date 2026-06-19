<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * LocalRole — Model pemetaan user SSO ke role lokal DPark Membership Service.
 *
 * Tabel ini menyimpan hasil pemetaan user yang login via SSO Dosen
 * ke sistem role internal DPark.
 */
class LocalRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'sso_sub',
        'email',
        'sso_roles',
        'local_role',
        'jwt_payload',
        'last_seen',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
    ];

    /**
     * Role-role yang tersedia di sistem lokal DPark Membership.
     */
    public const ROLES = [
        'admin'    => 'Administrator — Akses penuh ke semua fitur',
        'operator' => 'Operator — Bisa verifikasi membership',
        'member'   => 'Member — Akses terbatas ke data sendiri',
    ];
}
