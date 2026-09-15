<?php

use App\Enums\RoomStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('room_category_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('status')->default(RoomStatusEnum::Available->value)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'deleted_at']);
            $table->index('room_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
