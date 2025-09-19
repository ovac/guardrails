<div class="gr-card" x-data="guardrailsComponent()" x-init="init()">
  <div class="gr-header">
    <svg width="36" height="36" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="2.5" y="6" width="31" height="24" rx="8" stroke="#93c5fd" stroke-width="2" fill="rgba(147,197,253,0.12)" />
      <path d="M11 18L16 23L25 14" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <div>
      <h1>Guardrails Approvals</h1>
      <p class="gr-muted">Review and apply high-signal changes with confidence.</p>
    </div>
  </div>

  <div class="gr-meta">
    <h2>Pending Requests</h2>
    <button class="gr-button" @click="load()" :disabled="loading">
      <span x-show="!loading">Refresh</span>
      <span x-show="loading">Loading…</span>
    </button>
  </div>

  <template x-if="error">
    <div class="gr-item" style="border:1px solid #fca5a5; background:#fee2e2; color:#991b1b;">
      <strong>Unable to load approvals.</strong>
      <span class="gr-muted" x-text="error"></span>
    </div>
  </template>

  <template x-if="!error && items.length === 0">
    <p class="gr-muted">No pending requests right now. Enjoy the calm ✨</p>
  </template>

  <div class="gr-list" x-show="items.length > 0">
    <template x-for="req in items" :key="req.id">
      <div class="gr-item">
        <div class="gr-item-header">
          <div class="gr-item-title">Request #<span x-text="req.id"></span> · <span x-text="req.approvable_type || 'Unknown model'"></span></div>
          <div class="gr-muted">Captured <span x-text="formatDate(req.created_at)"></span> by <span x-text="req.initiator?.name || 'Unknown'"></span></div>
        </div>

        <details>
          <summary class="gr-muted">Show proposed changes</summary>
          <pre class="gr-json" x-text="pretty(req.new_data)"></pre>
        </details>

        <div class="gr-steps" x-show="(req.steps || []).length">
          <template x-for="step in (req.steps || [])" :key="step.id">
            <div class="gr-step">
              <div>
                <h3>Step <span x-text="step.level"></span> · <span x-text="step.name"></span></h3>
                <div class="gr-muted">Status: <strong x-text="step.status"></strong></div>
                <div class="gr-muted" x-show="step.meta?.signers">
                  Required: <span x-text="explainSigners(step.meta.signers)"></span>
                </div>
              </div>
              <button class="gr-button gr-button-primary"
                      @click="approve(req.id, step.id)"
                      :disabled="step.status !== 'pending' || loading">
                Approve step
              </button>
            </div>
          </template>
        </div>
      </div>
    </template>
  </div>
</div>
