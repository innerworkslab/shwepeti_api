<?php

namespace App\Services\Suppliers;

use App\Http\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Supplier::query()->filter($filters)->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data): SupplierResource
    {
        return DB::transaction(function () use ($data): SupplierResource {
            $supplier = Supplier::query()->updateOrCreate(['id' => $data['id'] ?? null], [
                'supplier_code' => $data['supplier_code'] ?? null,
                'name' => $data['name'],
                'phone_number' => $data['phone_number'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'bank_account_no' => $data['bank_account_no'] ?? null,
                'credit_limit_amount' => $data['credit_limit_amount'],
                'supplier_type' => $data['supplier_type'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            return new SupplierResource($supplier->refresh());
        });
    }

    public function delete(Supplier $supplier): void
    {
        DB::transaction(function () use ($supplier): void {
            if ((float) $supplier->payable_balance > 0) {
                throw ValidationException::withMessages([
                    'supplier' => ['A supplier with an outstanding payable balance cannot be deleted.'],
                ]);
            }

            $supplier->delete();
        });
    }

    public function toggleActive(Supplier $supplier, bool $isActive): SupplierResource
    {
        return DB::transaction(function () use ($supplier, $isActive): SupplierResource {
            $supplier->update(['is_active' => $isActive]);

            return new SupplierResource($supplier->refresh());
        });
    }

    public function restore(int $id): SupplierResource
    {
        return DB::transaction(function () use ($id): SupplierResource {
            $supplier = Supplier::withTrashed()->findOrFail($id);
            $supplier->restore();

            return new SupplierResource($supplier->refresh());
        });
    }
}
