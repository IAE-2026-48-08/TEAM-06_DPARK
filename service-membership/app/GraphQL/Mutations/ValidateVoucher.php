<?php

namespace App\GraphQL\Mutations;

use App\Services\VoucherService;

final class ValidateVoucher
{
    public function __construct(protected VoucherService $voucherService) {}

    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): array
    {
        $voucherCode   = $args['voucher_code'];
        $memberId      = (int) $args['member_id'];
        $subtotal      = (float) $args['subtotal'];
        $durationHours = (float) $args['duration_hours'];

        $result = $this->voucherService->validateVoucher($voucherCode, $memberId, $subtotal, $durationHours);

        if (!$result['valid']) {
            return [
                'success'         => false,
                'message'         => $result['message'],
                'voucher_code'    => $voucherCode,
                'is_valid'        => false,
                'discount_amount' => 0,
            ];
        }

        return [
            'success'           => true,
            'message'           => $result['message'],
            'voucher_code'      => $voucherCode,
            'is_valid'          => true,
            'discount_type'     => $result['discount_type'],
            'discount_value'    => $result['discount_value'],
            'discount_amount'   => $result['discount_amount'],
            'final_amount'      => max(0, $subtotal - $result['discount_amount']),
            'member_voucher_id' => $result['member_voucher_id'],
        ];
    }
}
