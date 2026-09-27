<?php

namespace App\Services\SupplierApLedgers;

use App\Enums\CashbookTransactionTypeEnum;
use App\Enums\SupplierApTransactionTypeEnum;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Models\SupplierApLedger;
use App\Models\SupplierApPayment;
use App\Models\User;
use App\Services\CashbookTransactions\CashbookTransactionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierApLedgerService
{
    public function __construct(private readonly CashbookTransactionService $cashbookTransactionService) {}

    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = SupplierApLedger::query()
            ->with(['supplier', 'creator'])
            ->filter($filters)
            ->latest('transaction_date')
            ->latest('id');

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function postStockIn(StockIn $stockIn, ?User $actor = null): void
    {
        DB::transaction(function () use ($stockIn, $actor): void {
            $supplier = Supplier::query()->whereKey($stockIn->supplier_id)->lockForUpdate()->firstOrFail();

            if (! $supplier->is_active) {
                throw ValidationException::withMessages(['supplier_id' => ['The supplier is inactive.']]);
            }

            if ((float) $stockIn->paid_amount > 0) {
                $this->cashbookTransactionService->post([
                    'cashbook_id' => $stockIn->cashbook_id,
                    'transaction_type' => CashbookTransactionTypeEnum::Expense->value,
                    'amount' => $stockIn->paid_amount,
                    'transaction_date' => $stockIn->transaction_date,
                    'remark' => "Stock-in payment {$stockIn->reference_no}",
                ], $actor, $stockIn);
            }

            $outstanding = (float) $stockIn->total_amount - (float) $stockIn->paid_amount;

            if ($outstanding <= 0) {
                return;
            }

            $newBalance = (float) $supplier->payable_balance + $outstanding;
            $this->ensureCreditLimit($supplier, $newBalance);
            $this->createLedger($supplier, SupplierApTransactionTypeEnum::Purchase, $outstanding, $newBalance, $stockIn, $stockIn->transaction_date, $stockIn->remark, $actor);
        });
    }

    public function postPayment(SupplierApPayment $payment, ?User $actor = null): void
    {
        DB::transaction(function () use ($payment, $actor): void {
            $supplier = Supplier::query()->whereKey($payment->supplier_id)->lockForUpdate()->firstOrFail();
            $newBalance = (float) $supplier->payable_balance - (float) $payment->amount;

            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Payment amount exceeds the supplier payable balance.'],
                ]);
            }

            $this->cashbookTransactionService->post([
                'cashbook_id' => $payment->cashbook_id,
                'transaction_type' => CashbookTransactionTypeEnum::Expense->value,
                'amount' => $payment->amount,
                'transaction_date' => $payment->payment_date,
                'remark' => "Supplier AP payment {$payment->reference_no}",
            ], $actor, $payment);

            $this->createLedger($supplier, SupplierApTransactionTypeEnum::Payment, (float) $payment->amount, $newBalance, $payment, $payment->payment_date, $payment->remark, $actor);
        });
    }

    private function createLedger(Supplier $supplier, SupplierApTransactionTypeEnum $type, float $amount, float $newBalance, StockIn|SupplierApPayment $reference, mixed $date, ?string $remark, ?User $actor): void
    {
        SupplierApLedger::query()->create([
            'supplier_id' => $supplier->id,
            'transaction_type' => $type->value,
            'amount' => $this->decimal($amount),
            'balance_after' => $this->decimal($newBalance),
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->id,
            'transaction_date' => $date,
            'remark' => $remark,
            'created_by' => $actor?->id,
        ]);

        $supplier->payable_balance = $this->decimal($newBalance);
        $supplier->save();
    }

    private function ensureCreditLimit(Supplier $supplier, float $newBalance): void
    {
        $limit = (float) $supplier->credit_limit_amount;

        if ($limit > 0 && $newBalance > $limit) {
            throw ValidationException::withMessages([
                'supplier_id' => ['This purchase exceeds the supplier credit limit.'],
            ]);
        }
    }

    private function decimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
