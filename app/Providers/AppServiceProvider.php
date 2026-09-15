<?php

namespace App\Providers;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUnitConversion;
use App\Models\Room;
use App\Models\RoomBed;
use App\Models\RoomCategory;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'room_category' => RoomCategory::class,
            'room' => Room::class,
            'room_bed' => RoomBed::class,
            'unit_group' => UnitGroup::class,
            'unit' => Unit::class,
            'item_category' => ItemCategory::class,
            'item' => Item::class,
            'item_unit_conversion' => ItemUnitConversion::class,
        ]);
    }
}
