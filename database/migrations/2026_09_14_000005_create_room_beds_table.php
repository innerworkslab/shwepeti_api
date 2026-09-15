<?php

use App\Enums\RoomBedTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('bed_type')->default(RoomBedTypeEnum::SingleBed->value);
            $table->unsignedInteger('qty');
            $table->timestamps();

            $table->unique(['room_id', 'bed_type']);
            $table->index('bed_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_beds');
    }
};
