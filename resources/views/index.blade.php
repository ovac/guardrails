@php($layout = config('guardrails.views.layout', config('human-approval.views.layout')))
@php($section = config('guardrails.views.section', config('human-approval.views.section', 'content')))

@if($layout)
  @extends($layout)
  @section($section)
@endif

<div class="container py-4" x-data="guardrails()">
  <div class="d-flex align-items-center mb-3">
    <img src="{{ asset('vendor/guardrails/images/logo.svg') }}" alt="Guardrails" style="height:28px" class="me-2"/>
    <h1 class="h4 m-0">Guardrails</h1>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 m-0">Pending Requests</h2>
        <button class="btn btn-sm btn-outline-secondary" @click="load()" :disabled="loading">
          <span x-show="!loading">Refresh</span>
          <span x-show="loading">Loading…</span>
        </button>
      </div>

      <template x-if="items.length === 0">
        <p class="text-muted">No pending requests.</p>
      </template>

      <div class="list-group">
        <template x-for="req in items" :key="req.id">
          <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-bold">#<span x-text="req.id"></span> • <span x-text="req.approvable_type"></span></div>
                <div class="small text-muted">Created <span x-text="new Date(req.created_at).toLocaleString()"></span></div>
                <details class="mt-2">
                  <summary>Changes</summary>
                  <pre class="mt-2 bg-light p-2 rounded" x-text="JSON.stringify(req.new_data, null, 2)"></pre>
                </details>
              </div>
              <div>
                <template x-for="step in (req.steps || [])" :key="step.id">
                  <div class="mb-2">
                    <div class="small">Step <span x-text="step.level"></span>: <strong x-text="step.name"></strong> — <em x-text="step.status"></em></div>
                    <button class="btn btn-sm btn-primary"
                            @click="approve(req.id, step.id)"
                            :disabled="step.status !== 'pending' || loading">
                      Approve
                    </button>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</div>

<script>
  function guardrails() {
    return {
      items: [],
      loading: false,
      apiBase: '/' + ({{ json_encode(trim(config('guardrails.route_prefix', 'staff/v1/guardrails'), '/')) }}) + '',
      load() {
        this.loading = true;
        fetch(this.apiBase, { headers: { 'Accept': 'application/json' }})
          .then(r => r.json())
          .then(j => { this.items = (j.data?.data) || []; })
          .finally(() => { this.loading = false; });
      },
      approve(requestId, stepId) {
        this.loading = true;
        fetch(`${this.apiBase}/${requestId}/steps/${stepId}/approve`, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]')||{}).content },
          body: new URLSearchParams()
        })
          .then(r => r.json())
          .then(() => this.load())
          .finally(() => { this.loading = false; });
      },
      init() { this.load(); }
    };
  }
</script>

@if($layout)
  @endsection
@endif

