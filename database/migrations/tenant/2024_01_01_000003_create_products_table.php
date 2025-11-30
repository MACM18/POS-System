<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->text('description')->nullable();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_threshold')->default(10);
            $table->uuid('category_id')->nullable();
            $table->string('unit')->default('piece'); // piece, kg, liter, etc.
            $table->string('image')->nullable();
            $table->json('images')->nullable(); // Additional images
            $table->json('attributes')->nullable(); // Color, size, etc.
            $table->boolean('is_active')->default(true);
            $table->boolean('track_inventory')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();

            $table->index('sku');
            $table->index('barcode');
            $table->index('is_active');
            $table->index('category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('products');
    }
};
