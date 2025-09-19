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
        // Top-level record for a guarded change.
        //
        // Stores the approvable morph target (model + id), the actor who
        // initiated the change. the request state, and JSON snapshots of
        // original and proposed values along with optional context/meta.
        Schema::create('guardrail_approval_requests', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Target of the approval (polymorphic relation).
            $table->nullableMorphs('approvable');

            // The authenticated user who initiated the change.
            $table->unsignedInteger('initiator_id')->nullable()->index()
                ->comment('ID of the user who initiated this request.');

            // Lifecycle state of the request.
            $table->enum('state', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending')
                ->comment('Current state of the approval request.');

            // Snapshots for diffing and audit.
            $table->string('description')->nullable()->comment('Human-readable summary of the captured change.');
            $table->json('new_data')->comment('Proposed values (subset of attributes).');
            $table->json('original_data')->nullable()->comment('Original values prior to change.');
            $table->json('context')->nullable()->comment('Route/event metadata for audit.');
            $table->json('meta')->nullable()->comment('Extensible metadata for custom needs.');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('guardrail_approval_requests');
    }
};
