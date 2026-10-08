<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('food_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 18, 2);
            $table->decimal('total', 18, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_order_items');
    }
};
