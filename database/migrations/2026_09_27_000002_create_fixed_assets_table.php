<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_no')->unique();
            $table->string('asset_code')->unique();
            $table->foreignId('asset_category_id')->constrained('asset_categories')->restrictOnDelete();
            $table->foreignId('cashbook_id')->constrained('cashbooks')->restrictOnDelete();
            $table->string('name');
            $table->string('serial_number')->nullable()->index();
            $table->string('location')->nullable();
            $table->dateTime('purchase_date');
            $table->decimal('purchase_amount', 18, 2);
            $table->boolean('depreciation_enabled')->default(false);
            $table->date('depreciation_start_date')->nullable();
            $table->unsignedInteger('useful_life_months')->nullable();
            $table->decimal('residual_value', 18, 2)->default(0);
            $table->decimal('accumulated_depreciation', 18, 2)->default(0);
            $table->date('warranty_expiry_date')->nullable();
            $table->json('documents')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('remark')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_category_id', 'purchase_date']);
            $table->index(['cashbook_id', 'purchase_date']);
            $table->index(['status', 'depreciation_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
