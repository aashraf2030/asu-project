<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('image_path');
            $table->float('temperature')->nullable();
            $table->integer('systolic_bp')->nullable();
            $table->integer('heart_rate')->nullable();
            $table->boolean('has_cough')->default(false);
            $table->boolean('has_headaches')->default(false);
            $table->boolean('can_smell_taste')->default(true);
            $table->enum('ai_status', ['Normal', 'Cardiomegaly', 'Pneumonia'])->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('differential_diagnosis')->nullable();  // JSON
            $table->integer('ai_confidence')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rays');
    }
};
