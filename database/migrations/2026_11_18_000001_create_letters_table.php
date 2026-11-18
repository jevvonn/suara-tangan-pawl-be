<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 3)->unique(); // 'A', '7', '10', '?' (stored uppercase)
            $table->string('category', 10)->storedAs($this->categoryExpression())->index();
            $table->string('image_url', 2048);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('letters');
    }

    /**
     * Generated-column expression that classifies a symbol as letter / number / symbol.
     * SQLite has no built-in REGEXP, so it gets an equivalent GLOB-based expression.
     */
    private function categoryExpression(): string
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return "CASE
                WHEN symbol <> '' AND symbol NOT GLOB '*[^A-Za-z]*' THEN 'letter'
                WHEN symbol <> '' AND symbol NOT GLOB '*[^0-9]*' THEN 'number'
                ELSE 'symbol'
            END";
        }

        return "CASE
            WHEN symbol REGEXP '^[A-Za-z]+$' THEN 'letter'
            WHEN symbol REGEXP '^[0-9]+$' THEN 'number'
            ELSE 'symbol'
        END";
    }
};
