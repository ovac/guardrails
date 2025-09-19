@php
    $layout = config('guardrails.views.layout');
    if (!is_string($layout)) {
        $layout = null;
    } else {
        $layout = trim($layout);
        if ($layout === '' || strtolower($layout) === 'null' || !\Illuminate\Support\Facades\View::exists($layout)) {
            $layout = null;
        }
    }
    $section = config('guardrails.views.section', 'content');
    $standalone = blank($layout);
@endphp

@if($standalone)
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Guardrails Approvals</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" />
    <style>
      :root {
        --gr-bg: #0f172a;
        --gr-card: #ffffff;
        --gr-border: #e2e8f0;
        --gr-muted: #64748b;
        --gr-primary: #2563eb;
        --gr-primary-dark: #1d4ed8;
      }
      body.gr-body {
        font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        margin: 0;
        background: linear-gradient(160deg, #0f172a 0%, #1e293b 40%, #0f172a 100%);
        color: #0f172a;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1.5rem;
      }
      .gr-shell {
        width: min(960px, 100%);
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(18px);
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 24px;
        padding: 2.5rem;
        box-shadow: 0 45px 90px -40px rgba(15, 23, 42, 0.55);
      }
      .gr-card {
        background: var(--gr-card);
        border-radius: 18px;
        padding: 2rem;
        box-shadow: 0 18px 45px -30px rgba(15, 23, 42, 0.35);
      }
      .gr-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #0f172a;
        margin-bottom: 1.75rem;
      }
      .gr-header h1 { font-weight: 600; font-size: 1.6rem; margin: 0; }
      .gr-meta { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1.5rem; }
      .gr-meta h2 { font-size:1rem; font-weight:600; margin:0; }
      .gr-button { appearance:none; border:1px solid var(--gr-border); background:#f8fafc; color:#0f172a; border-radius:999px; padding:0.45rem 1.15rem; font-size:0.85rem; font-weight:500; cursor:pointer; transition:all 0.2s ease; }
      .gr-button[disabled] { opacity:0.6; cursor:not-allowed; }
      .gr-button-primary { background:var(--gr-primary); border-color:transparent; color:#ffffff; }
      .gr-button-primary:hover { background:var(--gr-primary-dark); }
      .gr-list { display:grid; gap:1rem; }
      .gr-item { border:1px solid var(--gr-border); border-radius:16px; padding:1.25rem; display:grid; gap:0.75rem; background:#f8fafc; }
      .gr-item-title { font-weight:600; font-size:1rem; }
      .gr-muted { color:#64748b; font-size:0.85rem; }
      .gr-steps { display:grid; gap:0.75rem; }
      .gr-step { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:0.85rem 1rem; border-radius:12px; background:#e2e8f0; }
      .gr-json { background:#0f172a; color:#e2e8f0; border-radius:12px; padding:1rem; font-size:0.8rem; overflow-x:auto; }
      @media (max-width:640px) {
        body.gr-body { padding:1.5rem 1rem; }
        .gr-shell { padding:1.75rem; }
        .gr-step { flex-direction:column; align-items:stretch; }
        .gr-button { width:100%; }
      }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script>
      document.addEventListener('alpine:init', () => {
        Alpine.data('guardrails', guardrailsComponent);
      });
    </script>
  </head>
  <body class="gr-body">
    <div class="gr-shell">
      @include('guardrails::panel')
    </div>
    <script>
      function guardrailsComponent() {
        const apiBase = '/' + ({{ json_encode(trim(config('guardrails.route_prefix', 'guardrails/api'), '/')) }});
        return {
          items: [],
          loading: false,
          error: null,
          formatDate(value) {
            if (!value) return '—';
            try { return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); }
            catch (e) { return value; }
          },
          pretty(value) {
            try { return JSON.stringify(value, null, 2); }
            catch (e) { return String(value); }
          },
          explainSigners(meta = {}) {
            const perms = Array.isArray(meta.permissions) ? meta.permissions : [];
            const roles = Array.isArray(meta.roles) ? meta.roles : [];
            const parts = [];
            if (perms.length) parts.push(`${meta.permissions_mode === 'any' ? 'any' : 'all'} of ${perms.join(', ')}`);
            if (roles.length) parts.push(`${meta.roles_mode === 'any' ? 'any' : 'all'} of roles ${roles.join(', ')}`);
            return parts.length ? parts.join(' · ') : 'No additional constraints';
          },
          load() {
            this.loading = true;
            this.error = null;
            fetch(apiBase, { headers: { 'Accept': 'application/json' }})
              .then(r => { if (!r.ok) throw new Error(r.statusText || 'Request failed'); return r.json(); })
              .then(j => { this.items = (j.data?.data) || []; })
              .catch(err => { this.error = err.message || 'Unable to reach the approvals API.'; this.items = []; })
              .finally(() => { this.loading = false; });
          },
          approve(requestId, stepId) {
            this.loading = true;
            this.error = null;
            fetch(`${apiBase}/${requestId}/steps/${stepId}/approve`, {
              method: 'POST',
              headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') || {}).content || ''
              },
              body: new URLSearchParams()
            })
              .then(r => { if (!r.ok) throw new Error('Approval failed'); return r.json(); })
              .then(() => this.load())
              .catch(err => { this.error = err.message || 'Approval failed.'; this.loading = false; })
              .finally(() => { this.loading = false; });
          },
          init() { this.load(); }
        };
      }
    </script>
  </body>
</html>
@else
    @push('styles')
      <style>
        .gr-card { border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff; padding: 1.5rem; box-shadow: 0 18px 45px -30px rgba(15,23,42,0.35); }
        .gr-meta { display:flex; justify-content:space-between; align-items:center; gap:1rem; margin-bottom:1.25rem; }
        .gr-list { display:grid; gap:1rem; }
        .gr-item { border:1px solid #e2e8f0; border-radius:14px; padding:1.15rem; background:#f8fafc; }
        .gr-item-title { font-weight:600; font-size:1rem; }
        .gr-muted { color:#64748b; font-size:0.85rem; }
        .gr-step { display:flex; justify-content:space-between; align-items:center; gap:0.75rem; padding:0.75rem 0.9rem; border-radius:12px; background:#e2e8f0; }
        .gr-json { background:#0f172a; color:#e2e8f0; border-radius:10px; padding:0.85rem; font-size:0.78rem; overflow-x:auto; }
        .gr-button { border-radius:999px; padding:0.4rem 1.05rem; font-size:0.82rem; font-weight:500; border:1px solid #e2e8f0; background:#ffffff; transition:all .2s ease; }
        .gr-button[disabled] { opacity:0.6; cursor:not-allowed; }
        .gr-button-primary { background:#2563eb; color:#ffffff; border-color:transparent; }
      </style>
    @endpush
    @push('scripts')
      <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
      <script>
        document.addEventListener('alpine:init', () => {
          Alpine.data('guardrails', guardrailsComponent);
        });
      </script>
      <script>
        function guardrailsComponent() {
          const apiBase = '/' + ({{ json_encode(trim(config('guardrails.route_prefix', 'guardrails/api'), '/')) }});
          return {
            items: [],
            loading: false,
            error: null,
            formatDate(value) {
              if (!value) return '—';
              try { return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); }
              catch (e) { return value; }
            },
            pretty(value) {
              try { return JSON.stringify(value, null, 2); }
              catch (e) { return String(value); }
            },
            explainSigners(meta = {}) {
              const perms = Array.isArray(meta.permissions) ? meta.permissions : [];
              const roles = Array.isArray(meta.roles) ? meta.roles : [];
              const parts = [];
              if (perms.length) parts.push(`${meta.permissions_mode === 'any' ? 'any' : 'all'} of ${perms.join(', ')}`);
              if (roles.length) parts.push(`${meta.roles_mode === 'any' ? 'any' : 'all'} of roles ${roles.join(', ')}`);
              return parts.length ? parts.join(' · ') : 'No additional constraints';
            },
            load() {
              this.loading = true;
              this.error = null;
              fetch(apiBase, { headers: { 'Accept': 'application/json' }})
                .then(r => { if (!r.ok) throw new Error(r.statusText || 'Request failed'); return r.json(); })
                .then(j => { this.items = (j.data?.data) || []; })
                .catch(err => { this.error = err.message || 'Unable to reach the approvals API.'; this.items = []; })
                .finally(() => { this.loading = false; });
            },
            approve(requestId, stepId) {
              this.loading = true;
              this.error = null;
              fetch(`${apiBase}/${requestId}/steps/${stepId}/approve`, {
                method: 'POST',
                headers: {
                  'Accept': 'application/json',
                  'Content-Type': 'application/x-www-form-urlencoded',
                  'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') || {}).content || ''
                },
                body: new URLSearchParams()
              })
                .then(r => { if (!r.ok) throw new Error('Approval failed'); return r.json(); })
                .then(() => this.load())
                .catch(err => { this.error = err.message || 'Approval failed.'; this.loading = false; })
                .finally(() => { this.loading = false; });
            },
            init() { this.load(); }
          };
        }
      </script>
    @endpush

    @section($section)
      <div class="guardrails-panel">
        @include('guardrails::panel')
      </div>
    @endsection
@endif
