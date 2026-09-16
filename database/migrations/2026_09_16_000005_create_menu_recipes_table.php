<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_recipes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 18, 6);
            $table->decimal('base_quantity', 18, 6);
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['menu_id', 'item_id', 'unit_id']);
            $table->index('menu_id');
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_recipes');
    }
};
