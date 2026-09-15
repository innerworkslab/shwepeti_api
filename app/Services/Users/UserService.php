<?php

namespace App\Services\Users;

use App\Http\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = User::query()
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->where('is_active', true)->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data, ?User $actor = null): UserResource
    {
        return DB::transaction(function () use ($data, $actor): UserResource {
            $values = [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'is_active' => $data['is_active'] ?? true,
            ];

            if (! empty($data['password'])) {
                $values['password'] = $data['password'];
            }

            if ($actor) {
                $values[filled($data['id'] ?? null) ? 'updated_by' : 'created_by'] = $actor->id;
            }

            $user = User::query()->updateOrCreate(['id' => $data['id'] ?? null], $values);

            return new UserResource($user->refresh());
        });
    }

    public function delete(User $user, ?User $actor = null): void
    {
        DB::beginTransaction();

        try {
            if ($actor) {
                $user->forceFill(['deleted_by' => $actor->id])->save();
            }

            $user->delete();

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    public function restore(int $id, ?User $actor = null): User
    {
        DB::beginTransaction();

        try {
            $user = User::withTrashed()->findOrFail($id);
            $user->restore();

            if ($actor) {
                $user->forceFill([
                    'updated_by' => $actor->id,
                    'deleted_by' => null,
                ])->save();
            }

            DB::commit();

            return $user->refresh();
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }
}
