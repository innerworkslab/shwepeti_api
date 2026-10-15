<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_depreciations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained()->restrictOnDelete();
            $table->date('period');
            $table->decimal('amount', 18, 2);
            $table->decimal('opening_book_value', 18, 2);
            $table->decimal('closing_book_value', 18, 2);
            $table->timestamps();
            $table->unique(['fixed_asset_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_depreciations');
    }
};
