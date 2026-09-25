<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashbook_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('cashbook_id')->constrained('cashbooks')->restrictOnDelete();
            $table->string('transaction_type')->index();
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_after', 18, 2);
            $table->dateTime('transaction_date');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cashbook_id', 'transaction_date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashbook_transactions');
    }
};
