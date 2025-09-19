title: Extending Models & Migrations
description: Practical examples for adding columns, relations, and behaviors.

# Extending Models & Migrations

You can customize Guardrails’ tables and models to match your domain needs. This page shows how to add columns and use your own model class to get casts/relations while remaining compatible with the package.

## Add Columns to Migrations

After publishing, write a new migration that alters the Guardrails tables:

```php
// database/migrations/2025_09_16_000000_add_reason_to_approval_requests.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('guardrail_approval_requests', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('state');
            $table->foreignId('workspace_id')->nullable()->index()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('guardrail_approval_requests', function (Blueprint $table) {
            $table->dropColumn(['reason','workspace_id']);
        });
    }
};
```

Because the package models use `$guarded = []`, you can assign to these new columns without overriding fillables.

## Extend the Model in Your App

Create an app-level model that extends the package model to add casts, relations, or scopes:

```php
// app/Models/ApprovalRequest.php
namespace App\Models;

class ApprovalRequest extends \OVAC\Guardrails\Models\ApprovalRequest
{
    protected $casts = [
        'meta' => 'array',
        'reason' => 'string',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeForWorkspace($q, $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }
}
```

Use `App\Models\ApprovalRequest` in your code for advanced queries; the package will continue to work with its own model internally.

## Populate Custom Columns

Use package events to populate your new columns when a request is captured or completed:

```php
Event::listen(\OVAC\Guardrails\Events\ApprovalRequestCaptured::class, function ($e) {
    $e->request->reason = request('reason');
    $e->request->workspace_id = optional(auth()->user())->workspace_id;
    $e->request->save();
});
```

## Add Indexes for Performance

Consider indexing `state`, `initiator_id`, and any new foreign keys to speed up dashboards:

```php
Schema::table('guardrail_approval_requests', function (Blueprint $table) {
    $table->index(['state','initiator_id']);
});
```

## Customize Behavior With Policies or Gates

You can layer route middleware or gates to enforce extra rules beyond signer policies. For example, block approvals after business hours.
