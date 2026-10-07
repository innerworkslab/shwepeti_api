<?php

use App\Enums\BookingChargeTypeEnum;
use App\Enums\BookingStatusEnum;
use App\Enums\BookingTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_no')->unique();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->string('booking_type')->default(BookingTypeEnum::Reservation->value)->index();
            $table->string('charge_type')->default(BookingChargeTypeEnum::Day->value)->index();
            $table->string('guest_name');
            $table->string('guest_phone')->nullable();
            $table->string('guest_email')->nullable();
            $table->dateTime('expected_check_in_at');
            $table->dateTime('expected_check_out_at');
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('checked_out_at')->nullable();
            $table->string('status')->default(BookingStatusEnum::Reserved->value)->index();
            $table->unsignedInteger('guest_count')->default(1);
            $table->decimal('room_rate', 18, 2)->default(0);
            $table->unsignedInteger('session_hours')->nullable();
            $table->decimal('session_rate', 18, 2)->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance_amount', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('room_id');
            $table->index('expected_check_in_at');
            $table->index('expected_check_out_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
