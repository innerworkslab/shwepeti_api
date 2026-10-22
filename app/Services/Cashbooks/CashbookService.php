<?php

namespace App\Services\Cashbooks;

use App\Enums\CashbookTypeEnum;
use App\Http\Resources\Cashbooks\CashbookResource;
use App\Models\Cashbook;
use App\Models\CashbookClosing;
use App\Models\User;
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

    public function paginateForPos(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Cashbook::query()
            ->filter($filters)
            ->whereIn('type', [
                CashbookTypeEnum::PosCash->value,
                CashbookTypeEnum::PosBank->value,
            ])
            ->where('is_active', true)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->get();
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

            if ($openingBalanceChanged && ($cashbook->transactions()->exists() || $cashbook->closings()->exists())) {
                throw ValidationException::withMessages([
                    'opening_balance' => ['Opening balance cannot be changed after transactions or daily closings have been recorded.'],
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

            if ($cashbook->closings()->exists()) {
                throw ValidationException::withMessages([
                    'id' => ['A cashbook with daily closings cannot be deleted. Deactivate it instead.'],
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

    /**
     * @param  array{remark?: ?string}  $data
     */
    public function close(Cashbook $cashbook, array $data, User $actor, bool $forPos = false): CashbookClosing
    {
        return DB::transaction(function () use ($cashbook, $data, $actor, $forPos): CashbookClosing {
            $cashbook = Cashbook::query()->whereKey($cashbook->id)->lockForUpdate()->firstOrFail();
            $this->ensureAccessible($cashbook, $forPos);

            if (! $cashbook->is_active) {
                throw ValidationException::withMessages([
                    'cashbook_id' => ['An inactive cashbook cannot be closed.'],
                ]);
            }

            $closedAt = now();

            if ($cashbook->closings()->whereDate('closing_date', $closedAt->toDateString())->exists()) {
                throw ValidationException::withMessages([
                    'closing_date' => ['This cashbook has already been closed today.'],
                ]);
            }

            $previousClosing = $cashbook->closings()->latest('id')->first();

            return $cashbook->closings()->create([
                'closing_date' => $closedAt->toDateString(),
                'closed_at' => $closedAt,
                'opening_balance' => $previousClosing?->closing_balance ?? $cashbook->opening_balance,
                'closing_balance' => $cashbook->current_balance,
                'remark' => $data['remark'] ?? null,
                'created_by' => $actor->id,
            ]);
        }, 3);
    }

    /**
     * @param  array{page?: ?int, per_page?: ?int, date_from?: ?string, date_to?: ?string}  $filters
     */
    public function closings(Cashbook $cashbook, array $filters, bool $forPos = false): LengthAwarePaginator|Collection
    {
        $this->ensureAccessible($cashbook, $forPos);
        $query = $cashbook->closings()->latest('id');

        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('closing_date', '>=', $filters['date_from']);
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('closing_date', '<=', $filters['date_to']);
        }

        return isset($filters['page']) ? $query->paginate((int) ($filters['per_page'] ?? 15)) : $query->get();
    }

    private function ensureAccessible(Cashbook $cashbook, bool $forPos): void
    {
        abort_if($forPos && ! in_array($cashbook->type, [CashbookTypeEnum::PosCash, CashbookTypeEnum::PosBank], true), 404);
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
