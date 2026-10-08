<?php

use App\Enums\ServiceOrderStatusEnum;
use App\Enums\ServiceOrderTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_no')->unique();
            $table->string('type')->default(ServiceOrderTypeEnum::Amenity->value)->index();
            $table->string('status')->default(ServiceOrderStatusEnum::Pending->value)->index();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('booking_id');
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
