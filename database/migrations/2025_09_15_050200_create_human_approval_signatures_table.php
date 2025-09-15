<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('human_approval_signatures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('step_id')->index();
            $table->unsignedInteger('staff_id')->index();
            $table->enum('decision', ['approved','rejected','postponed'])->default('approved');
            $table->text('comment')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['step_id','staff_id']);
            $table->foreign('step_id')->references('id')->on('human_approval_steps')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_approval_signatures');
    }
};

