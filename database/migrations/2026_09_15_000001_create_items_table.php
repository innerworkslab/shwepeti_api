<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_category_id')->constrained('item_categories')->restrictOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable()->unique();
            $table->foreignId('stock_unit_id')->constrained('units')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->decimal('min_stock', 18, 6)->nullable();
            $table->decimal('price', 18, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('item_category_id');
            $table->index('stock_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
