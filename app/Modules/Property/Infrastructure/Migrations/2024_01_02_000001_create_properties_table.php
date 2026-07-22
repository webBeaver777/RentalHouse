<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('street');
            $table->string('building_number');
            $table->string('apartment_number')->nullable();
            $table->string('city');
            $table->string('postal_code', 10);
            $table->string('country', 2)->default('PL');
            $table->string('declaration_type')->default('owner');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
