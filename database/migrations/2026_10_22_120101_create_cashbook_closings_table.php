<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashbook_closings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cashbook_id')->constrained()->restrictOnDelete();
            $table->date('closing_date');
            $table->dateTime('closed_at');
            $table->decimal('opening_balance', 18, 2);
            $table->decimal('closing_balance', 18, 2);
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['cashbook_id', 'closing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashbook_closings');
    }
};
