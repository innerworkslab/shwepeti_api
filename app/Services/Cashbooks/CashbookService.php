<?php

namespace App\Services\Cashbooks;

use App\Http\Resources\Cashbooks\CashbookResource;
use App\Models\Cashbook;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashbookService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Cashbook::query()->filter($filters)->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data): CashbookResource
    {
        return DB::transaction(function () use ($data): CashbookResource {
            if (blank($data['id'] ?? null)) {
                $cashbook = Cashbook::query()->create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'opening_balance' => $data['opening_balance'],
                    'description' => $data['description'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                ]);

                return new CashbookResource($cashbook->refresh());
            }

            $cashbook = Cashbook::query()->whereKey($data['id'])->lockForUpdate()->firstOrFail();
            $openingBalanceChanged = $this->decimal($cashbook->opening_balance) !== $this->decimal($data['opening_balance']);

            if ($openingBalanceChanged && $cashbook->transactions()->exists()) {
                throw ValidationException::withMessages([
                    'opening_balance' => ['Opening balance cannot be changed after transactions have been posted.'],
                ]);
            }

            $cashbook->update([
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $data['type'],
                'opening_balance' => $data['opening_balance'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? $cashbook->is_active,
            ]);

            if ($openingBalanceChanged) {
                $cashbook->current_balance = $data['opening_balance'];
                $cashbook->save();
            }

            return new CashbookResource($cashbook->refresh());
        });
    }

    public function delete(Cashbook $cashbook): void
    {
        DB::transaction(function () use ($cashbook): void {
            $cashbook = Cashbook::query()->whereKey($cashbook->id)->lockForUpdate()->firstOrFail();

            if ($cashbook->transactions()->exists()) {
                throw ValidationException::withMessages([
                    'id' => ['A cashbook with transactions cannot be deleted. Deactivate it instead.'],
                ]);
            }

            $cashbook->delete();
        });
    }

    public function toggleActive(Cashbook $cashbook, bool $isActive): CashbookResource
    {
        return DB::transaction(function () use ($cashbook, $isActive): CashbookResource {
            $cashbook->update(['is_active' => $isActive]);

            return new CashbookResource($cashbook->refresh());
        });
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
