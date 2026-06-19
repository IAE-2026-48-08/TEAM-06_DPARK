<?php

namespace App\GraphQL\Mutations;

use App\Models\Member;
use App\Services\MembershipService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class StoreMember
{
    public function __construct(protected MembershipService $membershipService) {}

    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): Member
    {
        // Validasi input
        $validator = Validator::make($args, [
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:members,email',
            'phone'           => 'required|string|max:20',
            'vehicle_plate'   => 'required|string|max:20',
            'membership_type' => 'required|in:reguler,premium,vip',
            'expired_at'      => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $memberType       = $args['membership_type'];
        $discountPercent  = $this->membershipService->getDiscountByType($memberType);
        $memberNumber     = $this->membershipService->generateMemberNumber();

        return Member::create([
            'member_number'       => $memberNumber,
            'name'                => $args['name'],
            'email'               => $args['email'],
            'phone'               => $args['phone'],
            'vehicle_plate'       => strtoupper($args['vehicle_plate']),
            'membership_type'     => $memberType,
            'discount_percentage' => $discountPercent,
            'status'              => 'active',
            'joined_at'           => now(),
            'expired_at'          => $args['expired_at'] ?? null,
        ]);
    }
}
