<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_adjustment_id')->constrained('inventory_adjustments')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('system_quantity', 18, 6);
            $table->decimal('physical_quantity', 18, 6);
            $table->string('adjustment_type');
            $table->decimal('adjustment_quantity', 18, 6);
            $table->decimal('base_quantity', 18, 6);
            $table->string('batch_no')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index('inventory_adjustment_id');
            $table->index('item_id');
            $table->index('adjustment_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_items');
    }
};
