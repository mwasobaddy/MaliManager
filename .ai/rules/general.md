---
paths:
  - '**'
---

# General

## Commit messages follow one fixed shape
Every commit is `type(scope): imperative lowercase subject` — feat, fix, refactor, test, docs, chore, style, build, ci — with no full stop and no §/phase prefix; put a spec reference in trailing parentheses instead. Scope is the area the work actually lives in (platform, tenant, properties, payments, expenses, leases, ai, audit, plans, permissions, search, auth, ui, db, rules), not the directory the diff happens to start in.

After a blank line, up to three short paragraphs: what was wrong or missing before, what the change does, and the constraint or trade-off that shaped it. End with `Verified in the browser:` or `Verified by tests:` naming what was actually run — never claim a check you did not run, and say so plainly when nothing was verified.

The body is the record of intent, so do not fold it into the subject.

`.githooks/commit-msg` enforces the subject shape and warns when the body is missing; `composer run hooks` points git at it, and `composer run setup` does the same for a fresh clone.

Aim for 72 characters on the subject line. If it does not fit, the detail belongs in the body, not the subject.

Shape:

```
feat(admin): let an editor replace the homepage hero's portrait

The portrait was a constant in code, and the editor could not reach it: the
page editor offers a picture picker only to sections that already have a
picture, and the hero had none in the database.

The section's picture now wins over the shipped photograph, and the picker
appears for the sections whose component has a place to put one. The credit
goes with the photograph, because it names a photographer and a session and
would be false about a different picture.

Migration 0032 adds the database constraint: a section with a picture must
describe it. The action has refused one since the page editor was built; this
is the same rule one layer down.

Verified in the browser: the homepage renders the chosen picture in the blob
mask, the editor shows the picker with the image and its description, and the
live preview shows the real hero with the new picture.
```
