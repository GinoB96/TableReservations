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
        Schema::create('reservation_requests_tables', function (Blueprint $table) {
            $table->foreignId('reservation_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->primary(['reservation_request_id', 'table_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_requests_tables');
    }
};
