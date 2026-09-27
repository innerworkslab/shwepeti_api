<?php

namespace App\Services\SupplierApPayments;

use App\Enums\SupplierApPaymentStatusEnum;
use App\Http\Resources\SupplierApPayments\SupplierApPaymentResource;
use App\Models\SupplierApPayment;
use App\Models\User;
use App\Services\Inventory\InventoryDocumentService;
use App\Services\SupplierApLedgers\SupplierApLedgerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierApPaymentService
{
    public function __construct(
        private readonly InventoryDocumentService $inventoryDocumentService,
        private readonly SupplierApLedgerService $supplierApLedgerService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = SupplierApPayment::query()
            ->with(['supplier', 'cashbook', 'creator'])
            ->filter($filters)
            ->latest('payment_date');

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data, ?User $actor = null): SupplierApPaymentResource
    {
        return DB::transaction(function () use ($data, $actor): SupplierApPaymentResource {
            if (filled($data['id'] ?? null)) {
                $existing = SupplierApPayment::query()->findOrFail($data['id']);

                if ($existing->status !== SupplierApPaymentStatusEnum::Draft) {
                    throw ValidationException::withMessages(['id' => ['Only draft AP payments can be updated.']]);
                }
            }

            $values = [
                'supplier_id' => $data['supplier_id'],
                'cashbook_id' => $data['cashbook_id'],
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'remark' => $data['remark'] ?? null,
            ];

            if (blank($data['id'] ?? null)) {
                $values['reference_no'] = $data['reference_no'] ?? $this->inventoryDocumentService->referenceNo('SAP', SupplierApPayment::class, $data['payment_date']);
                $values['status'] = SupplierApPaymentStatusEnum::Draft->value;
                $values['created_by'] = $actor?->id;
            } elseif (filled($data['reference_no'] ?? null)) {
                $values['reference_no'] = $data['reference_no'];
            }

            $payment = SupplierApPayment::query()->updateOrCreate(['id' => $data['id'] ?? null], $values);

            return new SupplierApPaymentResource($payment->refresh()->load(['supplier', 'cashbook', 'creator']));
        });
    }

    public function delete(SupplierApPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            if ($payment->status !== SupplierApPaymentStatusEnum::Draft) {
                throw ValidationException::withMessages(['id' => ['Only draft AP payments can be deleted.']]);
            }

            $payment->delete();
        });
    }

    public function updateStatus(SupplierApPayment $payment, string $status, ?User $actor = null): SupplierApPaymentResource
    {
        return DB::transaction(function () use ($payment, $status, $actor): SupplierApPaymentResource {
            $payment = SupplierApPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== SupplierApPaymentStatusEnum::Draft) {
                throw ValidationException::withMessages(['status' => ['Only draft AP payments can change status.']]);
            }

            if ($status === SupplierApPaymentStatusEnum::Posted->value) {
                $this->supplierApLedgerService->postPayment($payment, $actor);
            }

            $payment->update(['status' => $status]);

            return new SupplierApPaymentResource($payment->refresh()->load(['supplier', 'cashbook', 'creator']));
        });
    }
}
