<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_category_id')->constrained('menu_categories')->restrictOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 18, 2);
            $table->decimal('cost_price', 18, 2)->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('menu_category_id');
            $table->index('is_available');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
