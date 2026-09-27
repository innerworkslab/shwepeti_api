<?php

namespace App\Services\CashbookTransactions;

use App\Enums\CashbookTransactionTypeEnum;
use App\Http\Resources\CashbookTransactions\CashbookTransactionResource;
use App\Models\Cashbook;
use App\Models\CashbookTransaction;
use App\Models\User;
use App\Services\Inventory\InventoryDocumentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashbookTransactionService
{
    public function __construct(private readonly InventoryDocumentService $inventoryDocumentService) {}

    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = CashbookTransaction::query()
            ->with(['cashbook', 'creator'])
            ->filter($filters)
            ->latest('transaction_date')
            ->latest('id');

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @return array<string, string>
     */
    public function summary(array $filters = []): array
    {
        if (blank($filters['cashbook_id'] ?? null)) {
            return [];
        }

        $cashbook = Cashbook::query()->findOrFail($filters['cashbook_id']);
        $openingBalance = (float) $cashbook->opening_balance;

        if (filled($filters['date_from'] ?? null)) {
            $openingBalance += $this->netMovement(
                CashbookTransaction::query()
                    ->where('cashbook_id', $cashbook->id)
                    ->whereDate('transaction_date', '<', $filters['date_from']),
            );
        }

        $closingQuery = CashbookTransaction::query()->where('cashbook_id', $cashbook->id);

        if (filled($filters['date_to'] ?? null)) {
            $closingQuery->whereDate('transaction_date', '<=', $filters['date_to']);
        }

        $closingBalance = (float) $cashbook->opening_balance + $this->netMovement($closingQuery);

        return [
            'opening_balance' => $this->decimal($openingBalance),
            'closing_balance' => $this->decimal($closingBalance),
        ];
    }

    public function post(array $data, ?User $actor = null, ?Model $reference = null): CashbookTransactionResource
    {
        return DB::transaction(function () use ($data, $actor, $reference): CashbookTransactionResource {
            $cashbook = Cashbook::query()->whereKey($data['cashbook_id'])->lockForUpdate()->firstOrFail();

            if (! $cashbook->is_active) {
                throw ValidationException::withMessages([
                    'cashbook_id' => ['Transactions cannot be posted to an inactive cashbook.'],
                ]);
            }

            $amount = (float) $data['amount'];
            $currentBalance = (float) $cashbook->current_balance;
            $newBalance = $data['transaction_type'] === CashbookTransactionTypeEnum::Income->value
                ? $currentBalance + $amount
                : $currentBalance - $amount;

            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient cashbook balance for this expense.'],
                ]);
            }

            $transaction = CashbookTransaction::query()->create([
                'reference_no' => $data['reference_no'] ?? $this->inventoryDocumentService->referenceNo('CBT', CashbookTransaction::class, $data['transaction_date']),
                'cashbook_id' => $cashbook->id,
                'transaction_type' => $data['transaction_type'],
                'amount' => $this->decimal($amount),
                'balance_after' => $this->decimal($newBalance),
                'transaction_date' => $data['transaction_date'],
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'remark' => $data['remark'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $cashbook->current_balance = $this->decimal($newBalance);
            $cashbook->save();

            return new CashbookTransactionResource($transaction->load(['cashbook', 'creator']));
        });
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function netMovement(Builder $query): float
    {
        $income = (float) (clone $query)
            ->where('transaction_type', CashbookTransactionTypeEnum::Income->value)
            ->sum('amount');
        $expense = (float) (clone $query)
            ->where('transaction_type', CashbookTransactionTypeEnum::Expense->value)
            ->sum('amount');

        return $income - $expense;
    }
}
