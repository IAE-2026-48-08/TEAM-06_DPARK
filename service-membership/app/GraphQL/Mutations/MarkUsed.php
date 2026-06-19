<?php

namespace App\GraphQL\Mutations;

use App\Services\VoucherService;

final class MarkUsed
{
    public function __construct(protected VoucherService $voucherService) {}

    /**
     * @param  null  $_
     * @param  array{}  $args
     */
    public function __invoke($_, array $args): array
    {
        $memberVoucherId = (int) $args['member_voucher_id'];
        $transactionId   = $args['transaction_id'];

        $success = $this->voucherService->markVoucherAsUsed($memberVoucherId, $transactionId);

        return [
            'success' => $success,
            'message' => $success ? 'Voucher berhasil ditandai sebagai terpakai.' : 'Gagal menandai voucher.',
        ];
    }
}
