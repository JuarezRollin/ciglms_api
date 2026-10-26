<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

abstract class Controller
{
    /**
     * @param  list<string>  $searchable
     * @param  list<string>  $sortable
     * @param  list<string>  $filterable
     */
    private const JSON_OPTIONS = JSON_INVALID_UTF8_SUBSTITUTE;

    public function success(?array $data, int $statusCode, array $headers = []): JsonResponse
    {
        $data = $data ?? [];
        $result = array_merge(['success' => true], $data);

        return response()->json($result, $statusCode, $headers, self::JSON_OPTIONS);
    }

    public function error(string $message, int $statusCode, ?string $errorCode = null, array $errors = [], array $headers = []): JsonResponse
    {
        $results = [
            'success' => false,
            'error_code' => $errorCode,
            'error_message' => $message,
        ];

        if (! empty($errors)) {
            $results['errors'] = $errors;
        }

        return response()->json($results, $statusCode, $headers, self::JSON_OPTIONS);
    }

    protected function paginate(
        Builder $query,
        ListRequest $request,
        array $searchable,
        array $sortable,
        array $filterable = [],
        string $defaultSort = 'created_at',
        string $defaultDirection = 'desc',
    ): LengthAwarePaginator {
        $validated = $request->validated();
        $sort = $validated['sort'] ?? $defaultSort;

        if (! in_array($sort, $sortable, true)) {
            throw ValidationException::withMessages([
                'sort' => ['The selected sort field is invalid. Allowed fields: '.implode(', ', $sortable).'.'],
            ]);
        }

        $filters = Arr::get($validated, 'filter', []);
        $unsupportedFilters = array_diff(array_keys($filters), $filterable);

        if ($unsupportedFilters !== []) {
            throw ValidationException::withMessages([
                'filter' => ['Unsupported filters: '.implode(', ', $unsupportedFilters).'.'],
            ]);
        }

        if (($search = $validated['search'] ?? null) !== null && $searchable !== []) {
            $query->where(function (Builder $query) use ($search, $searchable): void {
                foreach ($searchable as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', "%{$search}%");
                }
            });
        }

        foreach ($filters as $column => $value) {
            $query->where($column, $this->normalizeFilterValue($value));
        }

        $direction = $validated['direction'] ?? $defaultDirection;
        $query->orderBy($sort, $direction);
        if ($sort !== $query->getModel()->getKeyName()) {
            $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction);
        }

        return $query
            ->paginate((int) ($validated['per_page'] ?? config('api.pagination.default_per_page')))
            ->withQueryString();
    }

    private function normalizeFilterValue(string $value): string|bool
    {
        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }
}
