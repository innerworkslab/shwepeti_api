<?php

namespace App\Services\CashbookTransfers;

use App\Enums\CashbookTypeEnum;
use App\Models\Cashbook;
use App\Models\CashbookTransfer;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashbookTransferService
{
    public function paginate(array $filters, bool $forPos = false): LengthAwarePaginator|Collection
    {
        $query = $this->query($forPos)->filter($filters)->latest('id');

        return isset($filters['page']) ? $query->paginate((int) ($filters['per_page'] ?? 15)) : $query->get();
    }

    public function find(int $id, bool $forPos = false): CashbookTransfer
    {
        return $this->query($forPos)->with('transactions')->findOrFail($id);
    }

    private function query(bool $forPos): Builder
    {
        return CashbookTransfer::query()->with(['fromCashbook', 'toCashbook', 'creator'])
            ->when($forPos, fn (Builder $query) => $query->forPos());
    }

    /**
     * @param  array{request_id: string, from_cashbook_id: int, to_cashbook_id: int, amount: string|int|float, transfer_date: string, remark?: ?string}  $data
     */
    public function post(array $data, User $actor, bool $forPos = false): CashbookTransfer
    {
        try {
            return DB::transaction(function () use ($data, $actor, $forPos): CashbookTransfer {
                if ((int) $data['from_cashbook_id'] === (int) $data['to_cashbook_id']) {
                    throw ValidationException::withMessages(['to_cashbook_id' => ['Choose a different destination cashbook.']]);
                }

                $cashbooks = Cashbook::query()->whereIn('id', [$data['from_cashbook_id'], $data['to_cashbook_id']])
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                foreach (['from_cashbook_id', 'to_cashbook_id'] as $field) {
                    $cashbook = $cashbooks->get($data[$field]);
                    $label = $field === 'from_cashbook_id' ? 'source' : 'destination';

                    if (! $cashbook) {
                        throw ValidationException::withMessages([$field => ["The selected {$label} cashbook no longer exists. Choose another cashbook."]]);
                    }

                    if (! $cashbook->is_active) {
                        throw ValidationException::withMessages([$field => ["The selected {$label} cashbook is inactive. Choose an active cashbook."]]);
                    }

                    if ($forPos && $field === 'from_cashbook_id' && ! in_array($cashbook->type, [CashbookTypeEnum::PosCash, CashbookTypeEnum::PosBank], true)) {
                        throw ValidationException::withMessages([$field => ['POS transfers must start from a POS Cash or POS Bank cashbook. Choose a POS cashbook as the source.']]);
                    }
                }

                $source = $cashbooks->get($data['from_cashbook_id']);
                $destination = $cashbooks->get($data['to_cashbook_id']);
                $amount = BigDecimal::of((string) $data['amount'])->toScale(2);
                $sourceBalance = BigDecimal::of($source->current_balance)->minus($amount);
                $destinationBalance = BigDecimal::of($destination->current_balance)->plus($amount);

                if (! $amount->isPositive() || $sourceBalance->isNegative()) {
                    throw ValidationException::withMessages(['amount' => ['Insufficient cashbook balance or invalid transfer amount.']]);
                }
                if ($destinationBalance->isGreaterThan('9999999999999999.99')) {
                    throw ValidationException::withMessages(['amount' => ['The destination balance exceeds the supported limit.']]);
                }

                $transfer = CashbookTransfer::query()->create([
                    ...$data,
                    'amount' => (string) $amount,
                    'reference_no' => 'CBF-'.Str::uuid(),
                    'created_by' => $actor->id,
                ]);

                foreach ([[$source, 'expense', $sourceBalance, 'OUT'], [$destination, 'income', $destinationBalance, 'IN']] as [$cashbook, $type, $balance, $suffix]) {
                    $transfer->transactions()->create([
                        'reference_no' => $transfer->reference_no.'-'.$suffix,
                        'cashbook_id' => $cashbook->id,
                        'transaction_type' => $type,
                        'amount' => (string) $amount,
                        'balance_after' => (string) $balance,
                        'transaction_date' => $data['transfer_date'],
                        'remark' => $data['remark'] ?? null,
                        'created_by' => $actor->id,
                    ]);
                    $cashbook->current_balance = (string) $balance;
                    $cashbook->save();
                }

                return $transfer->load(['fromCashbook', 'toCashbook', 'creator', 'transactions']);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (CashbookTransfer::query()->where('request_id', $data['request_id'])->exists()) {
                throw ValidationException::withMessages(['request_id' => ['This transfer request has already been posted.']]);
            }

            throw $exception;
        }
    }
}
