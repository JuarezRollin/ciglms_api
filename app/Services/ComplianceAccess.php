<?php

namespace App\Services;

use App\Models\Compliance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ComplianceAccess
{
    /** @var array<string, array{role: string, kind: string}> */
    private const ROUTES = [
        'GL Processing to GL Creation' => ['role' => 'gl creation', 'kind' => 'gl'],
        'Voucher Creation to Encoder' => ['role' => 'encoder', 'kind' => 'gl'],
        'Reviewer to Voucher Creation' => ['role' => 'voucher creation', 'kind' => 'voucher'],
        'Box A Reviewer to Reviewer' => ['role' => 'reviewer', 'kind' => 'voucher'],
        'Budget to Compliance Officer' => ['role' => 'compliance', 'kind' => 'voucher'],
        'Accounting to Compliance Officer' => ['role' => 'compliance', 'kind' => 'voucher'],
        'Cash to Reviewer' => ['role' => 'reviewer', 'kind' => 'voucher'],
    ];

    /** @return list<string> */
    public function tabsFor(User $user): array
    {
        if ($user->hasRole('admin')) {
            return ['gl', 'voucher'];
        }

        $tabs = [];
        foreach (self::ROUTES as $route) {
            if ($user->hasRole($route['role'])) {
                $tabs[] = $route['kind'];
            }
        }

        return array_values(array_unique($tabs));
    }

    /** @return list<string> */
    public function typeNamesFor(User $user): array
    {
        if ($user->hasRole('admin')) {
            return array_keys(self::ROUTES);
        }

        $names = [];
        foreach (self::ROUTES as $name => $route) {
            if ($user->hasRole($route['role'])) {
                $names[] = $name;
            }
        }

        return $names;
    }

    public function canAccessType(User $user, string $typeName): bool
    {
        return $user->hasRole('admin')
            || (isset(self::ROUTES[$typeName]) && $user->hasRole(self::ROUTES[$typeName]['role']));
    }

    /** @return list<string> */
    public function kindsForType(string $typeName): array
    {
        return isset(self::ROUTES[$typeName]) ? [self::ROUTES[$typeName]['kind']] : ['gl', 'voucher'];
    }

    public function scopeVisible(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin')) {
            return $query;
        }

        $names = $this->typeNamesFor($user);

        return $query->whereHas('complianceType', fn (Builder $typeQuery): Builder => $typeQuery->whereIn('name', $names));
    }

    /** @return array{gl: int, voucher: int} */
    public function openCountsFor(User $user): array
    {
        $open = $this->scopeVisible(Compliance::query()->open(), $user);

        return [
            'gl' => (clone $open)->whereNotNull('guarantee_letter_id')->whereNull('batch_id')->count(),
            'voucher' => (clone $open)->whereNotNull('batch_id')->distinct()->count('batch_id'),
        ];
    }
}
