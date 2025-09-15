<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('human_approval_steps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('request_id')->index();
            $table->unsignedInteger('level');
            $table->string('name');
            $table->unsignedInteger('threshold')->default(1);
            $table->enum('status', ['pending','completed','rejected'])->default('pending');
            $table->json('meta')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('human_approval_requests')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_approval_steps');
    }
};

