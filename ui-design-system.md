# MaliManager UI Design System — "Warm Earth"

> **How to use this file:** This document is the canonical spec for MaliManager's visual language. When a prompt says *"review `ui-design-system.md` for context and redesign the `#component`"*, apply the tokens, state colors, and component rules defined here. Do not invent new hex values — extend the scales or reference existing tokens. Every color must satisfy WCAG AA (4.5:1 text, 3:1 large/UI) in BOTH light and dark mode.

---

## 1. Design Philosophy

- **Restraint = premium.** One brand color for identity (Terracotta), one structural color (Espresso), one rare "pop" (Teal). Functional/semantic colors are fixed and never double as brand.
- **60‑30‑10:** 60% neutral canvas (sand/charcoal), 30% structure + branding (espresso chrome, terracotta accents), 10% intentional action (teal pop + blue/green/red functional).
- **Calm, all‑day tool.** Warm‑shifted neutrals (not cold slate) signal "homes/property" and reduce eye strain for property managers who live in the app 8h/day.
- **Accessibility is the product.** WCAG AA minimum; never convey meaning by color alone — always pair with icon + label.
- **Dark mode is first‑class,** not an inversion. Warm off‑blacks, linen text, desaturated accents, elevation by lightness not shadows.
- **Multi‑tenant safe.** Only `--brand-primary` and `--brand-secondary` are tenant‑overridable. Neutrals, accent, and all functional/semantic colors are locked.

---

## 2. Brand Color Tokens

### 2.1 Light Mode

| Token | Hex | Role |
|---|---|---|
| `--brand-primary` | `#C87A53` | Terracotta — logo, active nav indicator, links, focus ring, primary CTA tint |
| `--brand-primary-600` | `#A85A36` | Terracotta **button fill** (white text ≈5:1, AA ✅) |
| `--brand-primary-100` | `#F6E7DE` | Terracotta tint — active‑nav bg, soft highlights |
| `--brand-secondary` | `#2E2622` | Deep Espresso — sidebar, top chrome, structural frame |
| `--brand-secondary-700` | `#3B2F2A` | Espresso hover/raised |
| `--brand-accent` | `#0E7C7E` | Teal "pop" — notifications, upsell, new‑feature (white text ≈5:1) |
| `--brand-accent-100` | `#D7EEED` | Teal tint — soft badge bg |
| `--bg-base` | `#F5F2EB` | Warm Sand — page background (60%) |
| `--bg-surface` | `#FAF8F5` | Soft Cream — cards, modals, inputs |
| `--bg-surface-hi` | `#FFFFFF` | Top of card gradient (see §3.4) |
| `--border` | `#E7E1D6` | Warm stone — dividers, input borders |
| `--border-strong` | `#D8CFBF` | Hover/active borders |
| `--text` | `#2A2621` | Dark Espresso — primary text (≈12:1 on sand) |
| `--text-muted` | `#6B635A` | Muted warm gray — secondary text, metadata |
| `--action-view` | `#2B6CB0` | Deep Sea Blue — "view/open" (white ≈5.3:1) |
| `--action-edit` | `#276749` | Forest Green — "edit" (white ≈5:1) |
| `--action-danger` | `#C53030` | Crimson — delete/destructive (white ≈5.2:1) |
| `--status-warning` | `#B7791F` | Amber — used as **soft badge**: bg `#FCEFCE`, text `#7C4A03` |
| `--status-success` | `#276749` | Green — soft badge bg `#DCF3E4`, text `#14532D` |
| `--status-danger` | `#C53030` | Red — soft badge bg `#FBE3E3`, text `#7A1414` |
| `--status-info` | `#2B6CB0` | Blue — soft badge bg `#DCEBF8`, text `#1A3E63` |
| `--focus-ring` | `#C87A53` | Terracotta focus outline (3:1 on surfaces) |

### 2.2 Dark Mode (`.dark` or `[data-theme="dark"]`)

| Token | Hex | Note |
|---|---|---|
| `--brand-primary` | `#E2956C` | Muted Terracotta — lightened for dark surfaces |
| `--brand-primary-600` | `#C87A53` | Button fill (dark text `#1A1917` ≈7:1) |
| `--brand-primary-100` | `#3A2A20` | Tint bg |
| `--brand-secondary` | `#14110D` | Deep warm black — sidebar |
| `--brand-secondary-700` | `#221C16` | Raised |
| `--brand-accent` | `#2BB3AC` | Teal lightened (dark text ≈5:1) |
| `--brand-accent-100` | `#13343A` | Tint |
| `--bg-base` | `#1A1917` | Charcoal (NOT pure black) |
| `--bg-surface` | `#242220` | Warm off‑black card |
| `--bg-surface-hi` | `#2E2B27` | Card top sheen |
| `--border` | `#36322D` | Warm divider |
| `--border-strong` | `#4A443C` | |
| `--text` | `#EAE6DF` | Soft Linen (NOT pure white) |
| `--text-muted` | `#A39B90` | (≈4.6:1 on charcoal ✅) |
| `--action-view` | `#5B9BD5` | lightened |
| `--action-edit` | `#4FA877` | lightened |
| `--action-danger` | `#E57373` | lightened (dark text) |
| `--status-warning` | `#E0A458` | soft bg `#3A2E12`, text `#F6E2B8` |
| `--status-success` | `#4FA877` | soft bg `#13301F`, text `#BFE8CC` |
| `--status-danger` | `#E57373` | soft bg `#3A1717`, text `#F4C4C4` |
| `--status-info` | `#5B9BD5` | soft bg `#16293D`, text `#C7DEF5` |
| `--focus-ring` | `#E2956C` | |

### 2.3 Contrast & AA Verification (key pairs)

| Pair | Ratio | AA |
|---|---|---|
| White on `--brand-primary-600` `#A85A36` | 5.0:1 | ✅ |
| Linen `#EAE6DF` on Espresso `#2E2622` | 10.8:1 | ✅ |
| White on Espresso `#2E2622` | 13.5:1 | ✅ |
| `--text` `#2A2621` on Sand `#F5F2EB` | 12:1 | ✅ |
| White on Teal `#0E7C7E` | 5.1:1 | ✅ |
| White on View `#2B6CB0` | 5.3:1 | ✅ |
| White on Edit `#276749` | 5.0:1 | ✅ |
| White on Danger `#C53030` | 5.2:1 | ✅ |
| *Avoid:* White on `--brand-primary` `#C87A53` (terracotta light) | 3.3:1 | ❌ use only for large text / non‑text |

### 2.4 Tonal Scales (generate 50–900 per family; name by purpose)

```
primary:      50 #F9F1EC  100 #F6E7DE  200 #EFD2C2  300 #E0B49B  400 #D29774
              500 #C87A53* 600 #A85A36* 700 #854628  800 #5E3220  900 #3A2015
secondary:    500 #2E2622* 600 #271F1B  700 #3B2F2A  800 #1C1714  900 #14110D*
accent:       100 #D7EEED  400 #2BB3AC  500 #0E7C7E* 600 #0B6567  700 #08484A
neutral(warm):50 #FBFAF7 100 #F5F2EB* 200 #E7E1D6* 300 #D8CFBF 400 #A39B90* 500 #6B635A* 700 #3B2F2A 900 #2A2621*
```
`*` = base token already defined above.

### 2.5 Multi‑Tenancy Rule

```css
:root { --brand-primary: #C87A53; --brand-secondary: #2E2622; }
/* Tenant override (DB‑driven, injected at login): */
.tenant-<id> { --brand-primary: <tenantColor>; --brand-secondary: <tenantColor>; }
```
Tenants may override **only** `--brand-primary` and `--brand-secondary` (the 30% branding layer). `--brand-accent`, all `--bg-*`, `--border`, `--text`, and every `--action-*` / `--status-*` are **protected** and uniform across tenants.

---

## 3. Global Foundations

### 3.1 Typography
- Font: one humanist sans (e.g. Inter / Source Sans) for UI; one mono (e.g. JetBrains Mono) for financial figures (rent, deposits, fees).
- Scale: `xs 12 / sm 13 / base 14 / md 16 / lg 18 / xl 22 / 2xl 28 / 3xl 36`. Line‑height 1.5 body, 1.2 headings.

### 3.2 Radius & Spacing
- Radius: `--radius: 10px` (cards/buttons), `--radius-sm: 6px` (inputs/chips), `--radius-lg: 16px` (modals/sheets). One radius system, used everywhere.
- Spacing scale: 4 / 8 / 12 / 16 / 24 / 32 / 48 (4px base).

### 3.3 Focus & Motion
- Focus: `outline: 2px solid var(--focus-ring); outline-offset: 2px;` on every interactive element. Never remove outline without a visible replacement.
- Motion: 150ms ease for hover, 200ms for open/close; respect `prefers-reduced-motion`.

### 3.4 Card — "Light shines from the top" (special)
Cards use a top‑down sheen to feel lit from above:

```css
.card {
  background:
    linear-gradient(180deg, var(--bg-surface-hi) 0%, var(--bg-surface) 38%);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow:
    inset 0 1px 0 0 rgb(255 255 255 / 0.55),   /* top edge highlight */
    0 1px 2px rgb(42 38 33 / 0.04),
    0 4px 12px rgb(42 38 33 / 0.06);            /* soft drop below */
  position: relative;
}
.card::before {  /* faint radial glow from top-center */
  content: ""; position: absolute; inset: 0; border-radius: inherit;
  background: radial-gradient(120% 60% at 50% 0%, rgb(255 255 255 / 0.45), transparent 70%);
  pointer-events: none;
}
.dark .card {
  background: linear-gradient(180deg, var(--bg-surface-hi) 0%, var(--bg-surface) 45%);
  box-shadow:
    inset 0 1px 0 0 rgb(255 255 255 / 0.06),
    0 1px 2px rgb(0 0 0 / 0.3),
    0 6px 18px rgb(0 0 0 / 0.35);
}
.dark .card::before {
  background: radial-gradient(120% 60% at 50% 0%, rgb(255 255 255 / 0.05), transparent 70%);
}
```

---

## 4. Component Specifications

> Template per component: **Surface / Border / Text / Accent** + **States** (default, hover, focus, active, disabled, error/destructive). Apply the same pattern to all.

### 4.1 Forms

**Button** — Surface `--brand-primary-600` (primary) or `--bg-surface`+`--border` (secondary/ghost); Text white / `--text`; Hover `--brand-primary-700` or `--border-strong`; Focus `--focus-ring`; Disabled `opacity .5 + cursor-not-allowed`. Destructive variant uses `--action-danger`. Shape: radius `--radius`, height 38px, weight 600. Never use plain `--brand-primary` `#C87A53` as a white‑text fill (fails AA) — use `-600`.

**Button Group** — Shared border; inner buttons lose adjacent borders; group border `--border`; active child gets `--brand-primary-100` bg + `--brand-primary` text.

**Checkbox** — Box `--bg-surface` border `--border-strong`; Checked bg `--brand-primary-600` + white tick; Focus `--focus-ring`; Disabled `opacity .5`. Always with `<Label>`.

**Combobox** — Input surface + popover (`--bg-surface`, `--border`, shadow); Option hover `--brand-primary-100`; Selected option `--brand-primary-600` text/bg; Empty → `Empty` component.

**Date Picker** — Built atop Calendar (see Data Display). Trigger = Input style; Selected day = `--brand-primary-600` bg white; Today = `--brand-primary` text + ring; Range = `--brand-primary-100` span.

**Field** — Wrapper providing label (`--text`), control (`--bg-surface`/border), hint (`--text-muted`), error (`--action-danger` text + `border` danger). Uses react‑hook‑form + zod; error state swaps border to `--status-danger` and shows icon + message.

**Form** — Layout container; fields stacked 16px gap; footer actions right‑aligned (primary Button + ghost "Cancel"). Validate via zod; invalid fields get `--status-danger` border + message; valid gets neutral.

**Input** — Surface `--bg-surface`; Border `--border` (hover `--border-strong`); Text `--text`; Placeholder `--text-muted`; Focus `border --brand-primary + ring`; Disabled `bg #F0ECE4 opacity .6`; Error `border --status-danger`. Radius `--radius-sm`, height 38px, 14px text.

**Input Group** — Input with prepended/appended addon (icon or text) in `--bg-base` + `--border`; addon text `--text-muted`.

**Input OTP** — Single‑char boxes (`--bg-surface`, `--border`); Filled → `--brand-primary-100` bg; Active → `--focus-ring`; Error → `--status-danger`.

**Native Select** — Styled `<select>`; surface/border like Input; chevron icon `--text-muted`; focus ring.

**Radio Group** — Radio dot `--brand-primary-600` when checked; ring `--focus-ring`; label `--text`.

**Select** — Custom listbox (not native); trigger = Input style; panel = Card‑like popover; options hover `--brand-primary-100`, selected `--brand-primary-600`.

**Slider** — Track `--border`; Filled portion `--brand-primary`; Thumb `--bg-surface` border `--brand-primary-600` + focus ring; Disabled `opacity .5`.

**Switch** — Track off `--border-strong`, on `--brand-primary-600`; Thumb `--bg-surface`; Focus `--focus-ring`; Disabled `opacity .5`.

**Textarea** — Same tokens as Input; min‑height 88px; resize vertical.

**Toggle** — Single on/off (same as Switch visual) or icon toggle; on = `--brand-primary-600`.

**Toggle Group** — Segmented; selected segment `--brand-primary-600` bg + white (or `--brand-primary-100` + `--brand-primary` for subtle); unselected `--text-muted`.

### 4.2 Data Display & Layout

**Avatar** — Circle `--bg-surface` + `--border`; Image or initials (`--brand-secondary` bg + linen text); Status dot uses `--status-*` at bottom‑right; Focus ring.

**Badge** — Soft semantic by default: bg `*-100`/tint, text dark `*-800/900` (AA ✅). Variants: `neutral` (`--bg-base`+`--text-muted`), `primary` (`--brand-primary-100`+`--brand-primary-700`), `accent` (`--brand-accent-100`+`--brand-accent`), `success/danger/warning/info` (semantic soft). Always pair color with text label.

**Calendar** — Grid `--bg-surface`; Day hover `--brand-primary-100`; Selected `--brand-primary-600` white; Today ring `--brand-primary`; Outside‑month `--text-muted`; Header nav buttons ghost.

**Card** — See §3.4 (top‑lit). Padding 20px; Header (`--text`, weight 600) + optional `#Card` actions top‑right; Footer separated by `--border`.

**Carousel** — Track `--bg-surface`; Arrows ghost buttons w/ focus ring; Dots active `--brand-primary-600`, inactive `--border-strong`; Captions `--text` on `--bg-surface` scrim.

**Chart** (Recharts) — Categorical series palette (distinct, colorblind‑safe): `[#C87A53, #0E7C7E, #2B6CB0, #276749, #B7791F, #C53030, #8B6F4E]`. Axis/text `--text-muted`; Grid `--border`; Tooltip = Card popover; Legend `--text`. Do not rely on hue alone — label series.

**Data Table** (TanStack) — Header `--bg-base` + bottom `--border-strong`, text `--text-muted` uppercase 12px; Row hover `--brand-primary-100`; Selected row `--brand-primary-100` + left `--brand-primary` bar; Striped alt `--bg-base`; Cells `--text`; Sort icon `--brand-primary` when active; Pagination uses Pagination component; Density toggle adjusts row height.

**Item** — List row: surface transparent → hover `--bg-base`; leading Avatar/icon; title `--text`, desc `--text-muted`; trailing Badge/action. Selected = `--brand-primary-100` bg + `--brand-primary` text.

**Separator** — `1px solid var(--border)` (horizontal) or `1px` vertical; muted, never pure black/white.

**Table** — Simple static table uses same tokens as Data Table header/rows; prefers Data Table for app data.

### 4.3 Navigation

**Breadcrumb** — Items `--text-muted`; Current `--text`; Separator `/` `--border-strong`; Links hover `--brand-primary`.

**Dropdown Menu** — Trigger ghost/secondary; Panel = Card popover (`--bg-surface`, `--border`, shadow); Item hover/active `--brand-primary-100` + `--brand-primary` text; Destructive item `--status-danger` text; Disabled `opacity .5`; Shortcut `<Kbd>` on right.

**Menubar** — Horizontal bar (top of app or under top nav); triggers ghost; active trigger `--brand-primary-100` bg; panels same as Dropdown.

**Navigation Menu** — Top‑level nav links; active = `--brand-primary` text + bottom 2px `--brand-primary` underline; hover `--text-muted`→`--text`; Flyout panel = Card.

**Pagination** — Buttons ghost; Current page `--brand-primary-600` bg white; Hover `--border-strong`; Disabled `opacity .4`.

**Sidebar** — **Background `--brand-secondary` (Espresso).** Logo mark top in `--brand-primary` (terracotta), wordmark `--text`→ linen `#EAE6DF` on espresso (≈10.8:1). Nav items: default text linen `#EAE6DF`/muted, hover `--brand-secondary-700` bg; **Active item = terracotta tint background (`--sidebar-accent`) + white text + medium weight** — no left border (kept calm/clean; the tint + icon is sufficient differentiation and avoids a boxy look). Section labels `--text-muted`. Footer user chip = Avatar + linen text. Collapsed mode keeps icon + tinted active background. (See §2.2 for dark espresso.)

**Tabs** — Tab strip border‑bottom `--border`; Active tab `--text` + 2px `--brand-primary` underline; Hover `--text-muted`; Disabled `opacity .4`.

### 4.4 Overlays, Modals & Disclosures

**Accordion** — Item header `--bg-surface`, border `--border`; Open chevron rotates, header text `--brand-primary` when open; Content `--text`.

**Alert** — Left accent border (4px) in semantic color; bg `*-100` tint; Icon + Title (`--text`) + description (`--text-muted`). Variants success/danger/warning/info; never color‑only (icon present).

**Alert Dialog** — Modal (Dialog) variant with title, description, and action buttons (Confirm = `--action-danger` or `--brand-primary-600`, Cancel = ghost). Focus trapped; backdrop `rgb(20 17 13 / .5)`.

**Collapsible** — Trigger `--text` + chevron; Content `--text-muted`; open state persists chevron rotation.

**Context Menu** — Right‑click popover = Dropdown Menu styling on `--bg-surface`.

**Dialog** — Centered modal: surface `--bg-surface` (top‑lit Card), border `--border`, shadow‑lg; Header (`--text`, weight 600) + close ghost button; Footer actions right‑aligned; Backdrop `rgb(20 17 13 / .5)`; Focus trap; Esc closes.

**Drawer** — Side panel (right default) = `--bg-surface` + left `--border`; same header/footer as Dialog; slides in 200ms; backdrop dim.

**Hover Card** — Small Card popover on hover/focus; surface `--bg-surface`, border `--border`, shadow; preview content `--text`/`--text-muted`.

**Popover** — Card‑style floating panel; arrow uses `--bg-surface`; trigger focus ring; dismiss on outside click/Esc.

**Sheet** — Edge panel variant of Dialog (bottom/left/right); same tokens.

**Tooltip** — Tiny `--brand-secondary` bg + linen text (≈10:1); 12px; appears on hover/focus with 150ms delay; never conveys required info by itself.

### 4.5 Feedback, Status & Messaging

**Attachment** — Chip/thumbnail `--bg-surface` + `--border`; remove `×` ghost → hover `--status-danger`; file icon `--brand-primary`.

**Bubble** — Chat bubble: outgoing = `--brand-primary-600` bg + white; incoming = `--bg-surface` + `--border` + `--text`; Avatar per side; tail optional.

**Empty** (EmptyMessage) — Centered: icon in `--brand-primary-100` circle, title `--text`, description `--text-muted`, optional primary action Button. Used for zero‑state across tables/lists.

**Message** — System/status line: `--text-muted` small; success/error use semantic soft badge styling inline.

**Message Scroller** — Scroll container (headless UI anchoring); background `--bg-base`; messages stack with 12px gap; auto‑anchor to bottom; new‑message pill = `--brand-accent`.

**Progress** — Track `--border`; Fill `--brand-primary-600`; Indeterminate uses `--brand-primary` shimmer; Label `--text-muted`. (Status‑colored variant uses semantic colors.)

**Questionnaire** — Multi‑step form container; progress bar at top (`--brand-primary-600`); steps dots `--brand-primary` active, `--border-strong` done, `--text-muted` pending; fields use Field tokens.

**Skeleton** — Shimmer blocks `--bg-base`→`--border` (or `#ECE6DB`); respects reduced motion (static). No color meaning.

**Spinner** — Stroke `--brand-primary-600` on `--bg-base`; size tokens sm/md/lg; aria‑label.

**Toast** — Fixed corner Card popover; left icon + soft semantic bg; title `--text`, desc `--text-muted`; action link `--brand-primary`; auto‑dismiss 4s, pause on hover; stack gap 8px.

### 4.6 Utilities, Command & Layout Control

**Aspect Ratio** — Box `--bg-surface` + `--border`; content centered.

**Command** (cmdk) — Palette Dialog; input = ghost Input; results list `--bg-surface`; active item `--brand-primary-100` + `--brand-primary` text; group label `--text-muted`; empty → Empty.

**Direction** — Logical‑property helper (LTR/RTL); no color.

**Kbd** — Inline key tag: `--bg-base` + `--border` + `--text-muted`, radius 6px, mono 12px; used for shortcuts.

**Label** — `--text` 14px/600; required asterisk `--status-danger`; associated control gets focus ring.

**Marker** — Map/location pin: `--brand-primary-600` fill + white; selected `--brand-accent`.

**Resizable** — Splitter handle `--border-strong` hover `--brand-primary`; panels `--bg-surface`.

**Scroll Area** — Custom scrollbar: thumb `--border-strong`, hover `--text-muted`; track transparent; no color semantics.

**Typography** — Heading scale uses `--text`; lead/body `--text`/`--text-muted`; `prose` links `--brand-primary`; code/mono `--text` on `--bg-base`.

---

## 5. Quick Reference — State Color Map

| State | Token |
|---|---|
| Default surface | `--bg-surface` |
| Hover surface | `--bg-base` / `--brand-primary-100` |
| Border | `--border` → `--border-strong` |
| Focus | `--focus-ring` (terracotta) |
| Primary action | `--brand-primary-600` |
| Brand accent / active nav | `--brand-primary` (indicator) |
| Sidebar chrome | `--brand-secondary` (espresso) |
| Pop / notification | `--brand-accent` (teal) |
| View / info | `--action-view` / `--status-info` |
| Edit / success | `--action-edit` / `--status-success` |
| Destructive / danger | `--action-danger` / `--status-danger` |
| Warning | `--status-warning` (soft) |
| Disabled | `opacity .5` + `cursor-not-allowed` |

---

*End of spec. When redesigning a component, read its entry in §4, apply the listed tokens for every state, verify AA contrast in light AND dark, and keep multi‑tenant‑protected tokens unchanged.*
