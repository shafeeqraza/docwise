<?php

namespace App\Services\V1\Common;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PaginatedResponseFormatter
{
    /**
     * Format a paginated response with consistent structure.
     *
     * @param LengthAwarePaginator $paginator
     * @param ResourceCollection|array $data The resource collection or array of items
     * @return array<string, mixed>
     */
    public static function format(LengthAwarePaginator $paginator, ResourceCollection|array $data): array
    {
        return [
            'data' => $data,
            'meta' => [
                'total' => $paginator->total(),
                'current_page' => $paginator->currentPage(),
                'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];
    }

    /**
     * Format a paginated response with resource collection.
     * Convenience method that automatically creates resource collection from items.
     *
     * @param LengthAwarePaginator $paginator
     * @param string $resourceClass The resource class name (e.g., DocumentResource::class)
     * @return array<string, mixed>
     */
    public static function formatWithResource(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        $collection = call_user_func([$resourceClass, 'collection'], $paginator->items());

        return self::format($paginator, $collection);
    }
}
