<?php

namespace App\Support;

final class ComplianceRoute
{
    /** @var array<string, array{role: string, status: string}> */
    private const RESUBMISSION_ROUTES = [
        'Voucher Creation to Encoder' => ['role' => 'encoder', 'status' => 'For Creation'],
        'Reviewer to Voucher Creation' => ['role' => 'voucher creation', 'status' => 'For Review by RMCD'],
        'Box A Reviewer to Reviewer' => ['role' => 'reviewer', 'status' => 'On Process'],
        'Budget to Compliance Officer' => ['role' => 'compliance', 'status' => 'For Obligation'],
        'Accounting to Compliance Officer' => ['role' => 'compliance', 'status' => 'For Pre-Audit'],
        'Cash to Reviewer' => ['role' => 'reviewer', 'status' => 'For Crediting'],
    ];

    /** @return array{role: string, status: string}|null */
    public static function resubmissionRoute(?string $complianceType): ?array
    {
        return self::RESUBMISSION_ROUTES[$complianceType] ?? null;
    }

    public static function resubmissionStatus(?string $complianceType): ?string
    {
        return self::resubmissionRoute($complianceType)['status'] ?? null;
    }
}
