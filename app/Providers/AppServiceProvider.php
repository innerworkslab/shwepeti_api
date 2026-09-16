<?php

namespace App\Providers;

use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferItem;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemUnitConversion;
use App\Models\Room;
use App\Models\RoomBed;
use App\Models\RoomCategory;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\StockOut;
use App\Models\StockOutItem;
use App\Models\Unit;
use App\Models\UnitGroup;
use App\Models\User;
use App\Models\Warehouse;
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
            'warehouse' => Warehouse::class,
            'stock_in' => StockIn::class,
            'stock_in_item' => StockInItem::class,
            'stock_out' => StockOut::class,
            'stock_out_item' => StockOutItem::class,
            'inventory_transfer' => InventoryTransfer::class,
            'inventory_transfer_item' => InventoryTransferItem::class,
            'inventory_adjustment' => InventoryAdjustment::class,
            'inventory_adjustment_item' => InventoryAdjustmentItem::class,
        ]);
    }
}
