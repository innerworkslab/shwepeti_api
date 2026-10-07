<?php

use App\Enums\BookingPaymentTypeEnum;
use App\Enums\PaymentMethodEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashbook_id')->constrained()->restrictOnDelete();
            $table->string('payment_no')->unique();
            $table->string('payment_type')->default(BookingPaymentTypeEnum::Deposit->value)->index();
            $table->string('payment_method')->default(PaymentMethodEnum::Cash->value)->index();
            $table->decimal('amount', 18, 2);
            $table->dateTime('paid_at');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('booking_id');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
