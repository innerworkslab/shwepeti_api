<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_ap_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('cashbook_id')->constrained('cashbooks')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->dateTime('payment_date');
            $table->string('status')->default('draft')->index();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'payment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ap_payments');
    }
};
