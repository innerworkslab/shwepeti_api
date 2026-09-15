<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_unit_conversions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('from_unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('to_unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('conversion_factor', 18, 6);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['item_id', 'from_unit_id', 'to_unit_id']);
            $table->index('item_id');
            $table->index('from_unit_id');
            $table->index('to_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_unit_conversions');
    }
};
