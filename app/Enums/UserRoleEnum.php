<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case HotelAdministrator = 'hotel_administrator';
    case FrontOfficeAdministrator = 'front_office_administrator';
    case ReservationAdministrator = 'reservation_administrator';
    case RestaurantAdministrator = 'restaurant_administrator';
    case CustomerServiceAdministrator = 'customer_service_administrator';
    case InventoryAdministrator = 'inventory_administrator';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
