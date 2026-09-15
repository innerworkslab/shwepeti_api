<?php

namespace App\Services\AuditLogs;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Models\Audit;

class AuditLogService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Audit::query()
            ->with('user')
            ->when($filters['event'] ?? null, fn ($query, string $event) => $query->where('event', $event))
            ->when($filters['user_id'] ?? null, fn ($query, mixed $userId) => $query->where('user_id', $userId))
            ->when($filters['auditable_type'] ?? null, fn ($query, string $type) => $query->where('auditable_type', $type))
            ->when($filters['auditable_id'] ?? null, fn ($query, mixed $id) => $query->where('auditable_id', $id))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('created_at');

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }
}
