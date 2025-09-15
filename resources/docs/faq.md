---
title: FAQ
description: Frequently asked questions about Guardrails.
tags: [faq]
---

# FAQ

## What if the initiator doesn’t have the required permission?

They won’t count and cannot sign. `includeInitiator(true, true)` only pre-approves when the initiator satisfies the signer rule. Otherwise, additional eligible staff must sign.

## Does “same-as-initiator” block when initiator lacks that permission?

Yes. The overlap is empty and no one satisfies the rule. Prefer `includeInitiator(true, true)` without the “same-as” constraint for a graceful path.

## Can I use token abilities instead of Spatie permissions?

Yes. Permissions are checked against token abilities when Spatie is unavailable. Role checks require Spatie.

