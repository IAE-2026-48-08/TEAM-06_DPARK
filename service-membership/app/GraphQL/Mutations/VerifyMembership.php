<?php

namespace App\GraphQL\Mutations;

use App\Services\MembershipService;

final class VerifyMembership
{
    public function __construct(protected MembershipService $membershipService) {}

    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): array
    {
        $vehiclePlate = $args['vehicle_plate'];
        $subtotal = $args['subtotal'] ?? null;

        $result = $this->membershipService->verifyMembership($vehiclePlate);

        $responseData = [
            'success'             => $result['valid'],
            'message'             => $result['message'],
            'vehicle_plate'       => $vehiclePlate,
            'is_member'           => $result['valid'],
        ];

        if ($result['valid'] && $result['member']) {
            $responseData['member_id']           = $result['member']->id;
            $responseData['member_number']       = $result['member']->member_number;
            $responseData['member_name']         = $result['member']->name;
            $responseData['membership_type']     = $result['member']->membership_type;
            $responseData['discount_percentage'] = $result['discount_percentage'];
            
            if ($subtotal !== null) {
                $calc = $this->membershipService->applyMembershipDiscount(
                    (float) $subtotal,
                    $result['discount_percentage']
                );
                $responseData['calculation'] = $calc;
            }
        }

        return $responseData;
    }
}
