<?php

namespace App\GraphQL\Mutations;

use App\Services\VoucherService;

final class ClaimVoucher
{
    public function __construct(protected VoucherService $voucherService) {}

    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): array
    {
        $memberId = (int) $args['member_id'];
        $voucherId = (int) $args['voucher_id'];

        $result = $this->voucherService->claimVoucher($memberId, $voucherId);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'data'    => $result['data'],
        ];
    }
}
