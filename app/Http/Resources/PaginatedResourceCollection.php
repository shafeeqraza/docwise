<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PaginatedResourceCollection extends ResourceCollection
{
    /**
     * The paginator instance.
     */
    protected LengthAwarePaginator $paginator;

    /**
     * Create a new resource collection instance.
     *
     * @param mixed $resource
     * @param LengthAwarePaginator $paginator
     */
    public function __construct($resource, LengthAwarePaginator $paginator)
    {
        parent::__construct($resource);
        $this->paginator = $paginator;
    }

    /**
     * Transform the resource collection into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->paginator->total(),
                'current_page' => $this->paginator->currentPage(),
                'next_page' => $this->paginator->hasMorePages() ? $this->paginator->currentPage() + 1 : null,
                'per_page' => $this->paginator->perPage(),
                'last_page' => $this->paginator->lastPage(),
            ],
            'links' => [
                'first' => $this->paginator->url(1),
                'last' => $this->paginator->url($this->paginator->lastPage()),
                'prev' => $this->paginator->previousPageUrl(),
                'next' => $this->paginator->nextPageUrl(),
            ],
        ];
    }
}
