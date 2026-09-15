<?php

namespace App\Services\Rooms;

use App\Http\Resources\Rooms\RoomResource;
use App\Models\Room;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoomService
{
    public function paginate(array $filters = []): LengthAwarePaginator|Collection
    {
        $query = Room::query()
            ->with(['roomCategory', 'beds'])
            ->filter($filters)
            ->latest();

        if (! isset($filters['page'])) {
            return $query->get();
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function save(array $data, ?User $actor = null): RoomResource
    {
        return DB::transaction(function () use ($data, $actor): RoomResource {
            $values = [
                'name' => $data['name'],
                'room_category_id' => $data['room_category_id'],
                'price' => $data['price'],
                'status' => $data['status'],
            ];

            if ($actor) {
                $values[filled($data['id'] ?? null) ? 'updated_by' : 'created_by'] = $actor->id;
            }

            $room = Room::query()->updateOrCreate(['id' => $data['id'] ?? null], $values);

            $bedTypes = collect($data['room_beds'])->pluck('bed_type')->all();
            $room->beds()->whereNotIn('bed_type', $bedTypes)->delete();

            foreach ($data['room_beds'] as $bedData) {
                $room->beds()->updateOrCreate(
                    ['bed_type' => $bedData['bed_type']],
                    ['qty' => $bedData['qty']],
                );
            }

            return new RoomResource($room->refresh()->load(['roomCategory', 'beds']));
        });
    }

    public function delete(Room $room, ?User $actor = null): void
    {
        DB::transaction(function () use ($room, $actor): void {
            if ($actor) {
                $room->forceFill(['deleted_by' => $actor->id])->save();
            }

            $room->delete();
        });
    }

    public function updateStatus(Room $room, string $status, ?User $actor = null): RoomResource
    {
        return DB::transaction(function () use ($room, $status, $actor): RoomResource {
            $values = ['status' => $status];

            if ($actor) {
                $values['updated_by'] = $actor->id;
            }

            $room->update($values);

            return new RoomResource($room->refresh()->load(['roomCategory', 'beds']));
        });
    }

    public function restore(int $id, ?User $actor = null): RoomResource
    {
        return DB::transaction(function () use ($id, $actor): RoomResource {
            $room = Room::withTrashed()->findOrFail($id);
            $room->restore();

            if ($actor) {
                $room->forceFill([
                    'updated_by' => $actor->id,
                    'deleted_by' => null,
                ])->save();
            }

            return new RoomResource($room->refresh()->load(['roomCategory', 'beds']));
        });
    }
}
