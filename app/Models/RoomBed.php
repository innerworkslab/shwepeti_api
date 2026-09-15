<?php

namespace App\Models;

use App\Enums\RoomBedTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['room_id', 'bed_type', 'qty'])]
class RoomBed extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'bed_type' => RoomBedTypeEnum::class,
            'qty' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
