<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashbook_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_no')->unique();
            $table->uuid('request_id')->unique();
            $table->foreignId('from_cashbook_id')->constrained('cashbooks')->restrictOnDelete();
            $table->foreignId('to_cashbook_id')->constrained('cashbooks')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->dateTime('transfer_date')->index();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashbook_transfers');
    }
};
