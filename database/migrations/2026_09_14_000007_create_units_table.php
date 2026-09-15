<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol');
            $table->boolean('is_base')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['unit_group_id', 'name']);
            $table->unique(['unit_group_id', 'symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
