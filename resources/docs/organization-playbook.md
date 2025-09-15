title: Organization Playbook
description: Patterns across Marketing, Sales, Finance, Legal/Sec, and Engineering.

# Organization Playbook

This playbook sketches how one company runs approvals end‑to‑end.

## Marketing: Content & Campaigns

- Blog post publish: author + one editor (see Use Cases).
- Campaign discount: tiered by depth (sales lead <20%, VP >=20%).

## Sales: Orders & Refunds

- Refund approval: one of Ops or Finance manager.
- Large order change: Ops approves first, then Finance.

## Finance: Payouts & Spend

- Payout double‑sign: two approvals with `payouts.approve`.
- Spend threshold: Ops first, CFO second for > $100k.

## Legal & Security: Policies & Data

- Policy update: Legal OR Security must sign.
- PII access change: Security + DPO two‑step.

## Engineering: Flags & Deploys

- Feature flag rollout: Ops gate then Engineering lead.
- Risky config change: peer with same permission must co‑sign.

## Decisions (Voting)

- Architecture RFC: threshold 3 from role `architect`.

## Practical Tips

- Start small: add a single guarded attribute and a one‑step flow.
- Keep flows close to the domain (models) when possible; use controller intercepts for external systems or orchestration.
- Use events to notify Slack/Email and to write audit logs (see Auditing & Changelog).

