<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Records a decision made by a reviewer on a specific step.
        Schema::create('guardrail_approval_signatures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('step_id')->index()->comment('Related step.');
            $table->unsignedInteger('signer_id')->index()->comment('Reviewer user id.');
            $table->enum('decision', ['approved', 'rejected', 'postponed'])->default('approved');
            $table->text('comment')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->json('meta')->nullable()->comment('Provider metadata or OTP, etc.');
            $table->timestamps();

            $table->unique(['step_id', 'signer_id']);
            $table->foreign('step_id')->references('id')->on('guardrail_approval_steps')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('guardrail_approval_signatures');
    }
};
