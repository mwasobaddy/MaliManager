---
paths:
    - 'app/Policies/**'
    - 'app/Http/Requests/**/Destroy*.php'
---

# Policies

## Do not create single-use policies

A policy earns its file through reuse or complexity. When one endpoint owns the
check — deleting a saved search only its owner can reach, a membership test
with no record-specific logic — assert it inline in the Form Request's
`authorize()` with a comment naming the rule. A policy with one method and one
caller is indirection without reuse.

Create the policy when the same record-level rule is enforced in two or more
places, or when the check needs tests of its own. If the route middleware is
already the gate, name it in `authorize()` instead of duplicating it; a policy
repeating the middleware's answer is a second representation that will drift.
