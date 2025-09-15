<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('human_approval_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->nullableMorphs('approvable');
            $table->unsignedInteger('actor_staff_id')->nullable()->index();
            $table->enum('state', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->json('new_data');
            $table->json('original_data')->nullable();
            $table->json('context')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_approval_requests');
    }
};

