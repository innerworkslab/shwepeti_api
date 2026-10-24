<?php

namespace App\Services\Bookings;

use App\Enums\BookingStatusEnum;
use App\Models\Booking;
use App\Models\RoomCategory;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class BookingDashboardService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function get(array $filters): array
    {
        $startDate = Carbon::parse($filters['start_date'])->startOfDay();
        $endDate = Carbon::parse($filters['end_date'])->endOfDay();
        $dates = $this->dates($startDate, $endDate);

        $categories = RoomCategory::query()
            ->with(['rooms' => fn ($query) => $query->orderBy('name')])
            ->when($filters['room_category_id'] ?? null, fn ($query, mixed $id) => $query->where('id', $id))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $roomIds = $categories
            ->flatMap(fn (RoomCategory $category) => $category->rooms->pluck('id'))
            ->values()
            ->all();
        $bookings = Booking::query()
            ->with('room.roomCategory')
            ->whereIn('room_id', $roomIds)
            ->where('expected_check_in_at', '<=', $endDate)
            ->where('expected_check_out_at', '>=', $startDate)
            ->whereIn('status', [
                BookingStatusEnum::CheckedOut->value,
                BookingStatusEnum::Reserved->value,
                BookingStatusEnum::CheckedIn->value,
            ])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('expected_check_in_at')
            ->get()
            ->groupBy('room_id');

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'dates' => $dates->map(fn (Carbon $date) => [
                'date' => $date->toDateString(),
                'label' => $date->format('D d'),
            ])->values()->all(),
            'room_categories' => $categories
                ->map(fn (RoomCategory $category) => $this->categoryPayload($category, $bookings, $dates))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Carbon>  $dates
     * @param  Collection<int, Collection<int, Booking>>  $bookings
     * @return array<string, mixed>
     */
    private function categoryPayload(RoomCategory $category, Collection $bookings, Collection $dates): array
    {
        $rooms = $category->rooms;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'availability' => $dates
                ->map(fn (Carbon $date) => $this->availabilityForDate($rooms, $bookings, $date))
                ->values()
                ->all(),
            'rooms' => $rooms
                ->map(fn ($room) => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'status' => $room->status?->value,
                    'price' => $room->price,
                ])
                ->values()
                ->all(),
            'bookings' => $rooms
                ->flatMap(fn ($room) => $bookings->get($room->id) ?? collect())
                ->map(fn (Booking $booking) => $this->bookingPayload($booking))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $rooms
     * @param  Collection<int, Collection<int, Booking>>  $bookings
     * @return array<string, mixed>
     */
    private function availabilityForDate(Collection $rooms, Collection $bookings, Carbon $date): array
    {
        $bookedRoomCount = $rooms
            ->filter(fn ($room) => ($bookings->get($room->id) ?? collect())
                ->contains(fn (Booking $booking) => $this->bookingOverlapsDate($booking, $date)))
            ->count();

        $totalRoomCount = $rooms->count();

        return [
            'date' => $date->toDateString(),
            'total_count' => $totalRoomCount,
            'booked_count' => $bookedRoomCount,
            'available_count' => max(0, $totalRoomCount - $bookedRoomCount),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPayload(Booking $booking): array
    {
        return [
            'roomId' => (string) $booking->room_id,
            'guestName' => $booking->guest_name,
            'start_date_time' => $booking->expected_check_in_at?->format('Y-m-d H:i'),
            'end_date_time' => $booking->expected_check_out_at?->format('Y-m-d H:i'),
            'status' => $booking->status ,
            'totalGuests' => $booking->guest_count,
            'colorCode' => $booking->status === BookingStatusEnum::CheckedIn ? '#10b981' : '#3b82f6',
        ];
    }

    private function bookingOverlapsDate(Booking $booking, Carbon $date): bool
    {
        return $booking->expected_check_in_at->startOfDay()->lte($date)
            && $booking->expected_check_out_at->startOfDay()->gte($date);
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function dates(Carbon $startDate, Carbon $endDate): Collection
    {
        return collect(CarbonPeriod::create($startDate, $endDate))
            ->map(fn (Carbon $date) => $date->copy()->startOfDay());
    }
}
