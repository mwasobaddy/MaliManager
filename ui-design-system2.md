# MaliManager UI Design System — "Warm Earth" v2

> **How to use this file:** canonical spec for MaliManager's visual language. When a prompt says _"review `ui-design-system.md` and redesign `#component`"_, apply the tokens in §2, the accent rules in §5, and the component rules in §6–7. **Do not invent hex values** — reference a token or extend a scale. Every text/background pair must pass WCAG AA (4.5:1 text, 3:1 large text and UI/graphics) in **both** light and dark mode. Tokens flip with the theme; components reference **token names**, never raw hex (except where §6 lists a resolved value).

---

## 0. What changed from v1

| Area | v1 | v2 |
|---|---|---|
| Links | Terracotta `#C87A53` (3.1:1 on cream, fails AA) | **Teal accent** (`--accent-text`, ≥6:1). Accent now owns wayfinding. |
| Terracotta as text | Used for links, active text, "Today" | New `--primary-text` (`#854628` / `#E2956C`). Raw `--brand-primary` is non-text only. |
| Accent usage | Notifications/upsell only | Links, applied filters, active sort, unread, tips, chart series 1, KPI sparklines, "View" row action (§5) |
| Row actions | Blue View / green Edit / red Delete fills | Neutral icon buttons; hover = accent / primary / danger. `--action-view`/`--action-edit` kept as deprecated aliases. |
| Input borders | `--border` (~1.2:1) | New `--border-input` (≥3:1) |
| Sidebar | Undefined `--sidebar-accent`, muted text 2.5:1 | Full sidebar token set; 3px active indicator restored |
| Status | 4 semantic colors | + `--status-neutral`, domain taxonomy (§7.2), "quiet" badge for normal states |
| Charts | 7 mixed series, fails 3:1 in dark | Separate light/dark palettes, 5 series + "other", CVD rules |
| Cards | `::before` glow painted over content | Gradients layered in `background` (no overlay) |
| Multi-tenancy | Only two hexes overridable, rest hard-coded | Derivation + contrast gating (§8) |

**New hex values introduced (all else reuses v1 hexes):** `--border-input` (`#8F8576` / `#80786E`), neutral badge (`#EDE8DE`, `#4A443C`, `#D1CAC0`), dark accent hover/on-tint (`#5CD0CA`, `#9BE0DC`), dark disabled/skeleton (`#2B2926`, `#322F2B`), chart violet/umber (`#7B5EA7`, `#B39DDB`, `#B89A72`).

---

## 1. Design Philosophy

- **Restraint = premium.** Terracotta for identity, Espresso for structure, Teal for wayfinding. Semantic colors are fixed and never double as brand.
- **Each color has one job:**

| Color | Job | Mnemonic |
|---|---|---|
| **Terracotta** | Brand, primary CTA, *selection*, current location | "what I do / what I picked" |
| **Espresso** | Structure (sidebar, tooltips, bulk-action bar) | "the frame" |
| **Teal** | Links, navigation, applied filters/sort, notifications, tips, data series 1 | "where I can go / what I'm looking at" |
| **Semantic** (green/amber/red/blue/gray) | Status and feedback only | "what state it's in" |
| **Warm neutrals** | Canvas, surfaces, borders, text | "the room" |

- **60-30-10.** 60% neutral canvas; 30% structure + brand (espresso chrome, terracotta); 10% intentional action (teal, semantic).
- **Calm all-day tool.** Property managers live in tables and dashboards 8h/day. Color is for **exceptions and wayfinding**; normal states render quiet (§7.2).
- **Accessibility is the product.** AA minimum; never meaning by color alone — always icon + label; links in running text are always underlined.
- **Dark mode is first-class**, not an inversion: warm off-blacks, linen text, lightened accents, elevation by lightness.
- **Multi-tenant safe.** Only `--brand-primary` and `--brand-secondary` are org-overridable; everything derived is computed and contrast-gated (§8).
- **Mobile is a primary surface** (caretakers, landlords on phones). Targets ≥44px on touch; inputs 16px on touch.

---

## 2. Color Tokens — Light vs Dark, and where each is used

### 2.1 Brand — Terracotta (identity, primary action, selection)

| Token | Light | Dark | Used in |
|---|---|---|---|
| `--brand-primary` | `#C87A53` | `#E2956C` | Logo mark, sidebar logo, active-nav indicator bar, progress shimmer, large text/icons. **Non-text only** |
| `--brand-primary-600` (fill) | `#A85A36` | `#C87A53` | Primary Button, checked Checkbox/Radio/Switch, selected Calendar day, current Pagination page, outgoing Bubble, Progress fill, Spinner, Marker, Slider filled, Toggle on |
| `--brand-primary-700` (hover fill) | `#854628` | `#E2956C` | Primary Button hover |
| `--on-primary` | `#FFFFFF` | `#1A1917` | Text/icon on `--brand-primary-600` |
| `--brand-primary-100` (tint) | `#F6E7DE` | `#3A2A20` | Selected row/item bg, active Button Group child, Badge `primary`, Empty icon circle, OTP filled, selected Dropdown/Select option |
| `--primary-text` | `#854628` | `#E2956C` | Text on tint (selected item, active Button Group child, "Today" date, Accordion open header, Badge `primary`) |
| `--focus-ring` | `#A85A36` | `#E2956C` | `outline` on every interactive element |

### 2.2 Structure — Espresso

| Token | Light | Dark | Used in |
|---|---|---|---|
| `--brand-secondary` | `#2E2622` | `#14110D` | Sidebar, Tooltip, Avatar initials bg, bulk-action bar |
| `--brand-secondary-700` | `#3B2F2A` | `#221C16` | Sidebar hover/raised, sidebar dividers |
| `--on-secondary` | `#EAE6DF` | `#EAE6DF` | Text on espresso (linen) |
| `--on-secondary-muted` | `#A39B90` | `#A39B90` | Section labels, secondary text on espresso |
| `--accent-on-inverse` | `#2BB3AC` | `#2BB3AC` | Links, counts, "Clear" actions **on espresso surfaces** |
| `--focus-ring-inverse` | `#E2956C` | `#E2956C` | Focus ring on espresso surfaces |

### 2.3 Accent — Teal (wayfinding, information, notification)

| Token | Light | Dark | Used in |
|---|---|---|---|
| `--brand-accent` (fill) | `#0E7C7E` | `#2BB3AC` | Accent Button (solid), notification count badge, "New" badge, new-message pill, selected Marker, unread dot |
| `--on-accent` | `#FFFFFF` | `#1A1917` | Text on `--brand-accent` |
| `--accent-text` | `#0B6567` | `#2BB3AC` | **All text links**, secondary links ("View all →"), table entity links, breadcrumbs (hover), Accent outline Button, active sort icon, applied-filter chip text border, empty-state links |
| `--accent-text-hover` | `#08484A` | `#5CD0CA` | Link hover/pressed |
| `--brand-accent-100` (tint) | `#D7EEED` | `#13343A` | Badge `accent`, Alert `tip`, applied-filter chip bg, accent Button hover bg, KPI sparkline area |
| `--accent-on-tint` | `#08484A` | `#9BE0DC` | Text on accent tint |
| Sequential ramp (heatmaps/occupancy) | 100 `#D7EEED` → 400 `#2BB3AC` → 500 `#0E7C7E` → 600 `#0B6567` → 700 `#08484A` | same hexes | Occupancy heatmap, density maps (single hue = neutral, no good/bad meaning) |

### 2.4 Neutrals — canvas, surfaces, text

| Token | Light | Dark | Used in |
|---|---|---|---|
| `--bg-base` | `#F5F2EB` | `#1A1917` | Page background, table header, Input Group addon, Kbd, neutral hover |
| `--bg-surface` | `#FAF8F5` | `#242220` | Cards, modals, inputs, popovers |
| `--bg-surface-hi` | `#FFFFFF` | `#2E2B27` | Card top sheen |
| `--bg-disabled` | `#F0ECE4` | `#2B2926` | Disabled Input/Select/Textarea |
| `--skeleton` | `#ECE6DB` | `#322F2B` | Skeleton blocks |
| `--border` | `#E7E1D6` | `#36322D` | Dividers, card/table borders, Separator |
| `--border-strong` | `#D8CFBF` | `#4A443C` | Hover borders, Button secondary border |
| `--border-input` | `#8F8576` | `#80786E` | Input/Textarea/Select borders, Checkbox box, Radio ring, Switch off-track, Slider track (≥3:1) |
| `--text` | `#2A2621` | `#EAE6DF` | Body, headings, table cells |
| `--text-muted` | `#6B635A` | `#A39B90` | Metadata, placeholders, table headers, hint text |
| `--overlay` | `rgb(20 17 13 / .5)` | `rgb(0 0 0 / .6)` | Dialog/Drawer/Sheet backdrop |

### 2.5 Semantic — status and feedback only

| Token | Light | Dark | Soft bg (L / D) | Soft text (L / D) | Used in |
|---|---|---|---|---|---|
| `--status-success` | `#276749` | `#4FA877` | `#DCF3E4` / `#13301F` | `#14532D` / `#BFE8CC` | Paid, Confirmed, Active; success Alert/Toast; Edit-success states |
| `--status-warning` | `#B7791F` | `#E0A458` | `#FCEFCE` / `#3A2E12` | `#7C4A03` / `#F6E2B8` | Partial, Expiring, Unmatched payment. **Icon/border only; never as text** |
| `--status-danger` | `#C53030` | `#E57373` | `#FBE3E3` / `#3A1717` | `#7A1414` / `#F4C4C4` | Overdue, Failed, destructive actions, field errors |
| `--status-info` | `#2B6CB0` | `#5B9BD5` | `#DCEBF8` / `#16293D` | `#1A3E63` / `#C7DEF5` | Open, Upcoming/due soon, Notice-to-vacate; info Alert |
| `--status-neutral` | `#6B635A` | `#A39B90` | `#EDE8DE` / `#2E2B27` | `#4A443C` / `#D1CAC0` | Pending, Draft, Under maintenance, "no data" |
| `--action-danger` | `#C53030` | `#E57373` | — | on-color: `#FFFFFF` / `#1A1917` | Destructive Button fill |
| `--action-view` _(deprecated alias)_ | → `--accent-text` | → `--accent-text` | | | Do not use for fills |
| `--action-edit` _(deprecated alias)_ | → `--primary-text` | → `--primary-text` | | | Do not use for fills |

### 2.6 Chart palette

| Token | Light | Dark | Notes |
|---|---|---|---|
| `--chart-1` | `#0E7C7E` | `#2BB3AC` | Default/first series (accent teal) |
| `--chart-2` | `#C87A53` | `#E2956C` | Brand terracotta. Use 2.5px strokes (3.1:1 on cream) |
| `--chart-3` | `#2B6CB0` | `#5B9BD5` | |
| `--chart-4` | `#7B5EA7` | `#B39DDB` | |
| `--chart-5` | `#8B6F4E` | `#B89A72` | |
| `--chart-other` | `#6B635A` | `#A39B90` | "Other"/remainder |
| Axis / labels | `--text-muted` | `--text-muted` | |
| Grid | `--border` | `--border` | |

Rules in §7.5. **Never** use red/green as categorical series; status charts (paid vs overdue) use `--status-*` plus labels/patterns.

---

## 3. CSS Tokens

```css
:root {
  /* brand — terracotta */
  --brand-primary: #c87a53;
  --brand-primary-600: #a85a36;
  --brand-primary-700: #854628;
  --brand-primary-100: #f6e7de;
  --primary-text: #854628;
  --on-primary: #ffffff;
  --focus-ring: #a85a36;

  /* structure — espresso */
  --brand-secondary: #2e2622;
  --brand-secondary-700: #3b2f2a;
  --on-secondary: #eae6df;
  --on-secondary-muted: #a39b90;
  --accent-on-inverse: #2bb3ac;
  --focus-ring-inverse: #e2956c;

  /* accent — teal */
  --brand-accent: #0e7c7e;
  --on-accent: #ffffff;
  --accent-text: #0b6567;
  --accent-text-hover: #08484a;
  --brand-accent-100: #d7eeed;
  --accent-on-tint: #08484a;

  /* neutrals */
  --bg-base: #f5f2eb;       --bg-surface: #faf8f5;   --bg-surface-hi: #ffffff;
  --bg-disabled: #f0ece4;   --skeleton: #ece6db;
  --border: #e7e1d6;        --border-strong: #d8cfbf; --border-input: #8f8576;
  --text: #2a2621;          --text-muted: #6b635a;
  --overlay: rgb(20 17 13 / 0.5);

  /* semantic */
  --status-success: #276749; --status-success-bg: #dcf3e4; --status-success-fg: #14532d;
  --status-warning: #b7791f; --status-warning-bg: #fcefce; --status-warning-fg: #7c4a03;
  --status-danger:  #c53030; --status-danger-bg:  #fbe3e3; --status-danger-fg:  #7a1414;
  --status-info:    #2b6cb0; --status-info-bg:    #dcebf8; --status-info-fg:    #1a3e63;
  --status-neutral: #6b635a; --status-neutral-bg: #ede8de; --status-neutral-fg: #4a443c;
  --action-danger: #c53030;  --on-danger: #ffffff;

  /* charts */
  --chart-1: #0e7c7e; --chart-2: #c87a53; --chart-3: #2b6cb0;
  --chart-4: #7b5ea7; --chart-5: #8b6f4e; --chart-other: #6b635a;

  /* sidebar */
  --sidebar-bg: var(--brand-secondary);
  --sidebar-border: var(--brand-secondary-700);
  --sidebar-hover: var(--brand-secondary-700);
  --sidebar-active-bg: #5e3220;
  --sidebar-active-indicator: var(--brand-primary);

  --radius: 10px; --radius-sm: 6px; --radius-lg: 16px;
}

.dark, [data-theme='dark'] {
  --brand-primary: #e2956c;
  --brand-primary-600: #c87a53;
  --brand-primary-700: #e2956c;
  --brand-primary-100: #3a2a20;
  --primary-text: #e2956c;
  --on-primary: #1a1917;
  --focus-ring: #e2956c;

  --brand-secondary: #14110d;
  --brand-secondary-700: #221c16;

  --brand-accent: #2bb3ac;
  --on-accent: #1a1917;
  --accent-text: #2bb3ac;
  --accent-text-hover: #5cd0ca;
  --brand-accent-100: #13343a;
  --accent-on-tint: #9be0dc;

  --bg-base: #1a1917;       --bg-surface: #242220;   --bg-surface-hi: #2e2b27;
  --bg-disabled: #2b2926;   --skeleton: #322f2b;
  --border: #36322d;        --border-strong: #4a443c; --border-input: #80786e;
  --text: #eae6df;          --text-muted: #a39b90;
  --overlay: rgb(0 0 0 / 0.6);

  --status-success: #4fa877; --status-success-bg: #13301f; --status-success-fg: #bfe8cc;
  --status-warning: #e0a458; --status-warning-bg: #3a2e12; --status-warning-fg: #f6e2b8;
  --status-danger:  #e57373; --status-danger-bg:  #3a1717; --status-danger-fg:  #f4c4c4;
  --status-info:    #5b9bd5; --status-info-bg:    #16293d; --status-info-fg:    #c7def5;
  --status-neutral: #a39b90; --status-neutral-bg: #2e2b27; --status-neutral-fg: #d1cac0;
  --action-danger: #e57373;  --on-danger: #1a1917;

  --chart-1: #2bb3ac; --chart-2: #e2956c; --chart-3: #5b9bd5;
  --chart-4: #b39ddb; --chart-5: #b89a72; --chart-other: #a39b90;

  --sidebar-active-bg: #3a2a20;
}
```

> `--on-secondary`, `--on-secondary-muted`, `--accent-on-inverse` and `--focus-ring-inverse` are identical in both modes because the espresso surfaces are dark in both.

---

## 4. Global Foundations

### 4.1 Typography & numbers

- UI font: one humanist sans (Inter / Source Sans). **Mono is for IDs and codes only** (M-Pesa receipt codes, unit/lease references, API keys), not for money.
- Money and metrics: `font-variant-numeric: tabular-nums`, right-aligned in tables, currency prefix muted and slightly smaller (`KES` at 0.85em `--text-muted`), thousands separators (`en-KE`).
- Scale: `xs 12 / sm 13 / base 14 / md 16 / lg 18 / xl 22 / 2xl 28 / 3xl 36`. Line-height 1.5 body, 1.2 headings. KPI values use `2xl`–`3xl`.
- **On touch devices inputs are 16px** (prevents iOS zoom); buttons/rows have 44px hit areas.

### 4.2 Radius, spacing, density

- Radius: `--radius` 10px (cards/buttons), `--radius-sm` 6px (inputs/chips), `--radius-lg` 16px (modals/sheets).
- Spacing: 4 / 8 / 12 / 16 / 24 / 32 / 48.
- Density: `comfortable` (default, 44px rows) and `compact` (36px rows) — a **global user preference**, applied to tables, lists and menus.

### 4.3 Focus & motion

- `outline: 2px solid var(--focus-ring); outline-offset: 2px;` on every interactive element. On espresso surfaces use `--focus-ring-inverse`.
- Motion: 150ms hover, 200ms open/close; honor `prefers-reduced-motion`.

### 4.4 Card — "light from the top" (fixed)

Gradients are layered on the card's own `background`, so nothing paints over content (v1's `::before` washed out text).

```css
.card {
  background:
    radial-gradient(120% 60% at 50% 0%, rgb(255 255 255 / 0.45), transparent 70%),
    linear-gradient(180deg, var(--bg-surface-hi) 0%, var(--bg-surface) 38%);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow:
    inset 0 1px 0 0 rgb(255 255 255 / 0.55),
    0 1px 2px rgb(42 38 33 / 0.04),
    0 4px 12px rgb(42 38 33 / 0.06);
}
.dark .card {
  background:
    radial-gradient(120% 60% at 50% 0%, rgb(255 255 255 / 0.05), transparent 70%),
    linear-gradient(180deg, var(--bg-surface-hi) 0%, var(--bg-surface) 45%);
  box-shadow:
    inset 0 1px 0 0 rgb(255 255 255 / 0.06),
    0 1px 2px rgb(0 0 0 / 0.3),
    0 6px 18px rgb(0 0 0 / 0.35);
}
/* Flat variant: use on cards that wrap dense tables, and in print */
.card--flat, .card--table { background: var(--bg-surface); box-shadow: none; }
@media print { .card { background: #fff; box-shadow: none; } }
```

### 4.5 Responsive

- ≥1024px: sidebar + content. <1024px: sidebar becomes a drawer; primary destinations in a bottom bar (max 5).
- <640px: Data Tables render as stacked cards (title row, 2–3 key fields, status badge, overflow menu); filters collapse into a Sheet.
- Dashboards: 12-col grid ≥1280, 6-col ≥768, 1-col below.

---

## 5. Accent Playbook — where teal is used

**Principle:** terracotta = *do / selected*; teal = *go / look / be told*. If the user can click it to **navigate or explore**, or the system is **informing** them without a status judgment, it's teal.

| Element | Light | Dark | Rule |
|---|---|---|---|
| Inline text link | `--accent-text` `#0B6567` | `#2BB3AC` | Always underlined in running text; hover `--accent-text-hover` |
| Secondary link ("View all →", "Manage", "See details") | `--accent-text` | `--accent-text` | Trailing arrow icon; underline on hover/focus |
| Table entity link (unit, tenant, property name) | `--accent-text`, weight 500 | same | Underline on hover/focus; the whole name is the target |
| Breadcrumb ancestors | `--text-muted` → hover `--accent-text` | same | Current page `--text` |
| Accent outline Button ("Export", "Send reminder", "Add note") | border + text `--accent-text`; hover bg `--brand-accent-100` | same tokens | For secondary-but-important actions that aren't destructive |
| Row action "View" | icon `--text-muted` → hover `--accent-text` | same | Neutral at rest |
| Active sort icon (table header) | `--accent-text` | same | Arrow shows direction; not color-only |
| Applied filter chip | bg `--brand-accent-100`, text `--accent-on-tint`, × icon | same tokens | "Clear all" is an accent link |
| Notification bell count / sidebar count | bg `--brand-accent`, text `--on-accent` (on espresso: `--accent-on-inverse` bg, `#14110D` text) | same | Numbers, not just a dot |
| Unread item | 6px `--brand-accent` dot + weight 600 | same | |
| "New" / feature badge | solid `--brand-accent` + `--on-accent` | same | Remove after 30 days |
| Tip / onboarding Alert | bg `--brand-accent-100`, 4px left border `--brand-accent`, icon `--accent-text` | same tokens | Distinct from `info` (blue = system information, teal = helpful guidance) |
| Empty-state link / secondary CTA | `--accent-text` | same | Primary CTA stays terracotta |
| KPI sparkline | stroke `--chart-1`, area `--brand-accent-100` | same tokens | |
| Collection-rate / occupancy meter | fill `--chart-1` | same | Task/loading Progress stays terracotta |
| Heatmap | sequential ramp §2.3 | same hexes | |
| Selected map marker | `--brand-accent` | same | Unselected = `--brand-primary-600` |
| Command palette "Go to…" group icon | `--accent-text` | same | |
| Sidebar links ("Help", "What's new") | `--accent-on-inverse` | same | |

**Never:** teal for destructive or success states · teal text on terracotta tint · terracotta for links · more than one *solid* teal element per view (counts/badges excepted).

---

## 6. Component Specifications

> Format per component: **Default / Hover / Selected-Active / Focus-Disabled-Error**. Token names flip with the theme; resolved hex is listed in the tables below where it matters.

### 6.1 Buttons (resolved values)

| Variant | Light fill / text / hover | Dark fill / text / hover | Use |
|---|---|---|---|
| **Primary** | `#A85A36` / `#FFFFFF` / `#854628` | `#C87A53` / `#1A1917` / `#E2956C` | One per view region |
| **Secondary (neutral)** — also **Cancel** | `--bg-base` + border `--border-strong` / `--text` / hover fill `--border` | same tokens | Cancel, Back, Close. In code: the neutral-fill variant (`shadcndefault`) |
| **Accent outline** | transparent, border + text `#0B6567` / hover bg `#D7EEED`, text `#08484A` | transparent, border + text `#2BB3AC` / hover bg `#13343A`, text `#9BE0DC` | Export, Send reminder, Add note |
| **Accent solid** (rare) | `#0E7C7E` / `#FFFFFF` / `#0B6567` | `#2BB3AC` / `#1A1917` / `#5CD0CA` | Upgrade, "Try the new reports" |
| **Ghost** | transparent / `--text` / hover `--bg-base` | same tokens | Toolbar, icon buttons |
| **Danger** | `#C53030` / `#FFFFFF` / `brightness(.92)` | `#E57373` / `#1A1917` / `brightness(1.08)` | Delete, Terminate lease |
| **Danger ghost** | text `--status-danger`, hover bg `--status-danger-bg` | same tokens | Opens a confirmation (does not destroy) |
| **Link** | `--accent-text`, underline | same | Inline actions |

Shape: radius `--radius`, height 38px (44px touch), weight 600. Disabled `opacity .5` + `cursor-not-allowed`. **Never** `#C87A53` with white text in light mode.

**Action Row** (`OverlayFooter`): right-aligned, `gap-2`, wraps; **Cancel left (Secondary), primary right**. Color by meaning, never position. Cancel is never ghost/outline. This rule applies to Form, Dialog, Alert Dialog, Sheet and Drawer (v1 contradicted itself).

### 6.2 Forms

- **Button Group** — shared border `--border`; active child `--brand-primary-100` bg + `--primary-text`.
- **Checkbox / Radio** — box/ring border `--border-input`, bg `--bg-surface`; checked fill `--brand-primary-600` + `--on-primary` tick; focus `--focus-ring`.
- **Combobox / Select** — trigger = Input; popover `--bg-surface` + `--border`; option hover `--bg-base`; **selected = check icon + `--primary-text`** on `--brand-primary-100`; keyboard-highlighted option = `--bg-base` + 2px inset `--focus-ring`; empty → Empty.
- **Date Picker / Calendar** — trigger = Input; selected day `--brand-primary-600` + `--on-primary`; today ring `--brand-primary` + `--primary-text`; range span `--brand-primary-100`; outside-month `--text-muted`; **event markers** use status dots (rent due = info, lease expiring = warning, overdue = danger) with legend.
- **Date-range presets** (This month / Last month / Quarter / Custom) — Toggle Group; selected `--brand-primary-100` + `--primary-text`.
- **Field** — label `--text` 14/600; hint `--text-muted`; error: border `--status-danger`, message `--status-danger` + icon; required asterisk `--status-danger`.
- **Form** — fields stacked, 16px gap; footer = Action Row.
- **Input / Textarea** — bg `--bg-surface`; border `--border-input`; hover border `--text-muted`; text `--text`; placeholder `--text-muted`; focus border `--brand-primary-600` + ring; disabled bg `--bg-disabled`; error border `--status-danger`. Height 38px (44 touch), radius `--radius-sm`.
- **Input Group** — addon bg `--bg-base`, border `--border-input`, text `--text-muted`. Currency fields: `KES` addon on the left.
- **Input OTP** — box `--bg-surface` + `--border-input`; filled `--brand-primary-100`; active `--focus-ring`; error `--status-danger`.
- **Native Select** — Input styling; chevron `--text-muted`.
- **Slider** — track `--border-input`; filled `--brand-primary-600`; thumb `--bg-surface` + 2px `--brand-primary-600`.
- **Switch / Toggle** — off track `--border-input`; on `--brand-primary-600`; thumb `--bg-surface`; icon Toggle on = `--brand-primary-100` + `--primary-text`.
- **Toggle Group** — selected `--brand-primary-600` + `--on-primary` (strong) or `--brand-primary-100` + `--primary-text` (subtle); unselected `--text-muted`.

### 6.3 Data display & layout

- **Avatar** — circle `--brand-secondary` + `--on-secondary` initials; status dot uses `--status-*` with a 2px `--bg-surface` ring.
- **Badge** — see §6.8 resolved table. Always icon + text; never color-only.
- **Card** — §4.4. Padding 20px; header `--text` 600 + actions top-right; footer divided by `--border`.
- **Carousel** — dots active `--brand-primary-600`, inactive `--border-strong`; arrows Ghost.
- **Chart** — §2.6 and §7.5.
- **Item (list row)** — hover `--bg-base`; selected `--brand-primary-100` + `--primary-text`; title `--text`, desc `--text-muted`; leading Avatar/icon; trailing Badge/action.
- **Separator** — `1px --border`.
- **Table / Data Table** — §7.3.

### 6.4 Navigation

- **Breadcrumb** — ancestors `--text-muted`, hover `--accent-text`; current `--text`; separator `--border-strong`.
- **Dropdown / Context Menu / Menubar** — panel = popover; item hover `--bg-base`; active `--brand-primary-100` + `--primary-text`; destructive item `--status-danger`; shortcuts in Kbd.
- **Navigation Menu** — active `--primary-text` + 2px `--brand-primary` underline; hover `--text-muted` → `--text`.
- **Pagination** — buttons Ghost; current `--brand-primary-600` + `--on-primary`; page-size select = Select.
- **Tabs** — border-bottom `--border`; active `--text` + 2px `--brand-primary` underline; hover `--text-muted`. Count chips inside tabs use Badge `neutral`.
- **Sidebar** — §6.7.
- **Top bar** — bg `--bg-surface`, bottom `--border`; contents left→right: org switcher, property switcher, global search (Command trigger + Kbd `⌘K`), notification bell (accent count), Avatar menu.

### 6.5 Overlays & disclosures

- **Dialog / Alert Dialog / Drawer / Sheet** — surface = top-lit Card; backdrop `--overlay`; header `--text` 600 + Ghost close; footer = Action Row (§6.1); focus trapped; Esc closes. Destructive confirms: primary = Danger, Cancel = Secondary.
- **Accordion** — header `--bg-surface` + `--border`; open header text `--primary-text`; content `--text`.
- **Alert** — 4px left border in semantic color, bg semantic soft, icon + title `--text` + desc `--text-muted`. Variants: success, danger, warning, info, **tip** (accent, §5).
- **Collapsible** — trigger `--text` + chevron; content `--text-muted`.
- **Hover Card / Popover** — Card popover; arrow `--bg-surface`.
- **Tooltip** — bg `--brand-secondary`, text `--on-secondary`; 12px; never the only carrier of required info.

### 6.6 Feedback, status & messaging

- **Toast** — Card popover + soft semantic bg; `role="status"` (success/info) or `role="alert"` (error). Success/info auto-dismiss at **8s**, pause on hover/focus; **errors and toasts with actions persist** until dismissed. Payment confirmations also land in the notification center. Action link `--accent-text`.
- **Empty** — icon in `--brand-primary-100` circle, title `--text`, desc `--text-muted`, primary CTA terracotta, secondary link `--accent-text`.
- **Progress** — task/loading: track `--border`, fill `--brand-primary-600`. Data meters (collection rate, occupancy): fill `--chart-1`, value label `--text`.
- **Skeleton** — `--bg-base` → `--skeleton` shimmer (static under reduced motion).
- **Spinner** — `--brand-primary-600` on `--bg-base`; `aria-label` required.
- **Bubble / Message / Attachment** — outgoing bubble `--brand-primary-600` + `--on-primary`; incoming `--bg-surface` + `--border`; attachment icon `--accent-text`, remove × hover `--status-danger`; new-message pill `--brand-accent` + `--on-accent`.
- **Questionnaire** — progress `--brand-primary-600`; step dots: active `--brand-primary`, done `--border-strong`, pending `--text-muted`.

### 6.7 Sidebar & chrome (resolved values)

Sidebar is espresso in **both** modes.

| Part | Light mode | Dark mode |
|---|---|---|
| Background | `#2E2622` | `#14110D` |
| Divider / hover bg | `#3B2F2A` | `#221C16` |
| Logo mark | `#C87A53` | `#E2956C` |
| Wordmark, nav text | `#EAE6DF` | `#EAE6DF` |
| Section labels | `#A39B90` | `#A39B90` |
| **Active item** bg | `#5E3220` | `#3A2A20` |
| **Active indicator** (3px, inset pill) | `#C87A53` | `#E2956C` |
| Active text | `#FFFFFF`, weight 600 | `#FFFFFF`, weight 600 |
| Count badge | bg `#2BB3AC`, text `#14110D` | same |
| Utility links (Help, What's new) | `#2BB3AC` | `#2BB3AC` |
| Focus ring | `#E2956C` | `#E2956C` |
| Footer user chip | Avatar + `#EAE6DF` | same |

Active state = tint **+ 3px indicator + weight 600 + `aria-current="page"`** (the tint alone is only ~1.4:1 against the sidebar and v1's tint-only active state failed "never color alone"). Collapsed mode keeps the icon, tint and indicator. Include the org switcher at the top and a property switcher beneath it.

### 6.8 Badges (resolved values)

| Variant | Light bg / text | Dark bg / text | Icon color |
|---|---|---|---|
| neutral | `#EDE8DE` / `#4A443C` | `#2E2B27` / `#D1CAC0` | `--status-neutral` |
| primary | `#F6E7DE` / `#854628` | `#3A2A20` / `#E2956C` | `--primary-text` |
| accent | `#D7EEED` / `#08484A` | `#13343A` / `#9BE0DC` | `--accent-text` |
| success | `#DCF3E4` / `#14532D` | `#13301F` / `#BFE8CC` | `#276749` / `#4FA877` |
| warning | `#FCEFCE` / `#7C4A03` | `#3A2E12` / `#F6E2B8` | `#B7791F` / `#E0A458` |
| danger | `#FBE3E3` / `#7A1414` | `#3A1717` / `#F4C4C4` | `#C53030` / `#E57373` |
| info | `#DCEBF8` / `#1A3E63` | `#16293D` / `#C7DEF5` | `#2B6CB0` / `#5B9BD5` |

**Quiet variant** (no fill): colored icon + `--text` label. Used for normal states in dense tables (§7.2).

### 6.9 Utilities

- **Command (cmdk)** — palette Dialog; active item `--brand-primary-100` + `--primary-text`; group labels `--text-muted`; "Go to…" icons `--accent-text`.
- **Kbd** — `--bg-base` + `--border` + `--text-muted`, mono 12px.
- **Label** — `--text` 14/600.
- **Marker** — `--brand-primary-600` + `--on-primary`; selected `--brand-accent`.
- **Resizable** — handle `--border-strong`, hover `--brand-primary`.
- **Scroll Area** — thumb `--border-strong`, hover `--text-muted`.
- **Typography** — headings `--text`; body `--text`/`--text-muted`; prose links `--accent-text` underlined; code `--text` on `--bg-base`.

---

## 7. Dashboard & Data Patterns

### 7.1 KPI card

- Label: `--text-muted` 13px. Value: `--text`, `2xl`–`3xl`, `tabular-nums`. Currency prefix muted.
- Delta chip: arrow icon + `±x%` + "vs last month", soft badge. **Color follows polarity, not direction** — arrears ↑ = danger, collections ↑ = success, vacancy ↑ = warning, flat = neutral.
- Optional sparkline: stroke `--chart-1`, area `--brand-accent-100`, no axes, 40px tall.
- The whole card may link to the detail view: title link `--accent-text` with trailing arrow.
- **Overview row (default):** Collected this month · Outstanding/arrears · Occupancy · Open maintenance.

### 7.2 Status taxonomy (property domain)

Color is for **exceptions**. Normal states are *quiet*; exceptions are *soft badges*.

| Domain | State | Variant | Icon |
|---|---|---|---|
| **Rent** | Paid | success, **quiet** | check-circle |
| | Partial | warning | circle-half |
| | Overdue (+ "N days") | danger | alert-circle |
| | Due soon (≤3 days) | info | clock |
| | Not yet due | neutral, quiet | circle |
| **Unit** | Occupied | success, **quiet** | home |
| | Vacant | warning | door-open |
| | Notice to vacate | info | calendar-clock |
| | Under maintenance | neutral | wrench |
| | Reserved | info | bookmark |
| **Lease** | Active | success, quiet | file-check |
| | Expiring (≤60 days) | warning | hourglass |
| | Expired | danger | file-x |
| | Draft | neutral | file-pen |
| **Maintenance** | Open | info | circle-dot |
| | In progress | neutral | loader |
| | Resolved | success, quiet | check |
| | Overdue | danger | alert-triangle |
| **M-Pesa** | Pending (STK sent) | neutral + spinner | loader |
| | Confirmed | success | check-circle |
| | Failed | danger | x-circle |
| | Reversed | warning | undo |
| | Unmatched (needs allocation) | warning | link-2-off |

### 7.3 Table anatomy

- **Header:** bg `--bg-base`, bottom `--border-strong`, text `--text-muted` 12px uppercase; sticky; sticky first column on horizontal scroll. Active sort: icon `--accent-text` + `aria-sort`.
- **Rows:** cells `--text`; divider `--border`; **hover `--bg-base`** (neutral); **selected `--brand-primary-100` + 3px left bar `--brand-primary`**; no zebra by default.
- **Entity links** (unit, tenant, property): `--accent-text`, weight 500, underline on hover/focus.
- **Numbers:** right-aligned, `tabular-nums`, header right-aligned too. Arrears: amount in `--text` + overdue badge (not a wall of red).
- **Codes** (M-Pesa receipt, lease ref): mono, `--text-muted`, copy icon button.
- **Row actions:** neutral icon buttons (`--text-muted`), hover → View `--accent-text`, Edit `--primary-text`, Delete `--status-danger`. Overflow menu beyond three actions. Each has a tooltip + `aria-label`.
- **Bulk-action bar** (appears on selection): fixed bottom of the table, bg `--brand-secondary`, text `--on-secondary`, "N selected" + Ghost-inverse actions; "Clear" link `--accent-on-inverse`; destructive action `--status-danger`-on-inverse only with confirmation.
- **Toolbar:** search Input · filter Selects · date-range · density Toggle · export (Accent outline). Applied filters show as accent chips beneath with "Clear all".
- **Footer:** result count `--text-muted`, Pagination, page-size Select.
- **States:** loading = Skeleton rows; empty = Empty with context action ("Add your first unit"); error = Alert danger + Retry; filtered-empty = "No results" + "Clear filters" accent link.
- **Mobile (<640px):** stacked cards; status badge top-right; overflow menu for actions.

### 7.4 Dashboard layout & widget states

- **Hierarchy:** (1) KPI row → (2) trend chart + **Needs attention** queue (overdue rent, expiring leases, unmatched M-Pesa payments, urgent maintenance) → (3) recent payments / activity table.
- **Needs attention** items are Item rows with a status badge and one inline action (Send reminder · Record payment · Allocate · Assign), using Accent outline or Primary buttons.
- **Every widget** has: skeleton, empty, error + Retry, and an "Updated 2 min ago" stamp (important for M-Pesa callbacks).
- **Role views** (landlord overview, property manager, caretaker, renter portal) reuse the same tokens and differ in density and the top actions only.

### 7.5 Charts

- Max **5 series + "other"**. Order: `--chart-1` → `-5`. Direct-label series where possible; legend otherwise.
- Vary line dash/markers or fill pattern so series don't rely on hue alone (CVD).
- Strokes 2.5px; bars with 2px gaps; gridlines `--border`; axes `--text-muted`.
- Tooltips = Card popover with series swatch + label + value.
- Status charts (paid / partial / overdue) use `--status-*` plus labels; **never red/green as categories**.
- Heatmaps / occupancy density: sequential ramp accent 100 → 700 (§2.3).
- Recharts: enable `accessibilityLayer`; offer a "View as table" toggle.
- Dark mode uses the dark chart tokens automatically (all ≥3:1 against `--bg-surface`).

---

## 8. Multi-Tenancy

**Terminology:** the SaaS customer is an **organization** (`org`), not a "tenant" — in this product *tenant* means the renter. Use `.org-<id>`, `orgBrand`, `org_theme` in code.

Orgs may override **only** `--brand-primary` and `--brand-secondary`. Everything else is locked: `--brand-accent`, all neutrals, all `--status-*`, `--action-danger`, charts.

### 8.1 Derivation (server-side, injected at login)

Compute server-side (not with runtime CSS color functions) so older Android browsers render correctly.

| Derived token | Rule |
|---|---|
| `--brand-primary-600` (fill) | Adjust lightness in OKLCH until white **or** dark text reaches ≥4.5:1; keep hue and chroma |
| `--on-primary` | Whichever of `#FFFFFF` / `#1A1917` passes 4.5:1 on the fill |
| `--brand-primary-700` | Fill darkened one step (hover) |
| `--brand-primary-100` | Tint at ~92% lightness, same hue |
| `--primary-text` | Light: darkened until ≥4.5:1 on `--bg-base`, `--bg-surface` **and** `--brand-primary-100`. Dark: lightened until ≥4.5:1 on `--bg-surface` and dark tint |
| `--focus-ring` | ≥3:1 on `--bg-base` and `--bg-surface` |
| `--brand-secondary` | If neither `--on-secondary` (linen) nor dark text reaches 4.5:1, darken until linen passes |
| `--sidebar-active-bg`, `--sidebar-active-indicator` | Derived from `--brand-primary` (dark tint + indicator) |
| Dark-mode set | Run the same derivation against the dark neutrals |

### 8.2 Onboarding guardrails

- Live preview: button, link, badge, sidebar, chart, in light **and** dark.
- If a chosen color can't produce a passing set, **auto-adjust and show the adjusted value**; never save a failing set.
- **Collision check:** if the org primary's hue is within ~30° of the locked teal accent, warn and suggest a nearby alternative (accent stays locked).
- Org logos: require light-on-dark and dark-on-light variants (or provide a neutral container), accept sanitized SVG.

---

## 9. Accessibility & Contrast

### 9.1 Key pairs (WCAG ratios, computed; re-verify in CI)

| Pair | Light | Dark |
|---|---|---|
| `--on-primary` on `--brand-primary-600` | 5.0:1 (`#FFF` on `#A85A36`) | 5.3:1 (`#1A1917` on `#C87A53`) |
| `--primary-text` on `--bg-base` | 6.5:1 | 7.3:1 |
| `--primary-text` on `--brand-primary-100` | 6.0:1 | 5.7:1 |
| `--accent-text` on `--bg-surface` | 6.5:1 | 6.2:1 |
| `--accent-text` on `--bg-base` | 6.1:1 | 6.8:1 |
| `--accent-on-tint` on `--brand-accent-100` | 8.5:1 | 8.9:1 |
| `--on-accent` on `--brand-accent` | 5.0:1 | 6.8:1 |
| `--text-muted` on `--bg-base` | 5.3:1 | 6.4:1 |
| `--text-muted` on `--bg-surface-hi` | 5.6:1 | 5.1:1 |
| `--border-input` on `--bg-surface` | 3.4:1 | 3.7:1 |
| `--focus-ring` on `--bg-base` | 4.5:1 | 7.3:1 |
| Sidebar: `--on-secondary-muted` on espresso | 5.4:1 | 6.9:1 |
| Sidebar: `--accent-on-inverse` on espresso | 5.8:1 | 7.3:1 |
| Sidebar: active indicator on espresso | 4.5:1 | 6.2:1 |
| Chart-1…5 on `--bg-surface` | ≥3.1:1 | ≥3.4:1 |

_Avoid:_ white on `--brand-primary` `#C87A53` (3.3:1) · `--status-warning` as text · `--accent-text` links in running text without an underline (only ~2.2:1 against body text) · any pale tenant color without derivation.

### 9.2 PR checklist for UI changes

- [ ] Uses tokens only; no new hex values
- [ ] Verified in light **and** dark
- [ ] Text ≥4.5:1; icons/borders/charts ≥3:1; input borders use `--border-input`
- [ ] Meaning conveyed by icon + label, not color alone
- [ ] Links in running text underlined; link vs. primary-action roles respected (teal vs terracotta)
- [ ] Keyboard: visible focus, logical order, `aria-current`/`aria-sort`/`aria-label` where needed
- [ ] Touch targets ≥44px, inputs 16px on touch
- [ ] Loading, empty, error and stale-data states present on widgets
- [ ] Tested with an org that has an unusual brand color (very pale, very saturated)
- [ ] Reduced-motion respected

---

## 10. Quick Reference

| Need | Token |
|---|---|
| Primary action / selection | `--brand-primary-600` / `--brand-primary-100` + `--primary-text` |
| Brand mark, active indicator | `--brand-primary` (non-text) |
| Link / secondary link / entity link | `--accent-text` (hover `--accent-text-hover`) |
| Applied filter / tip / sparkline | `--brand-accent-100` + `--accent-on-tint` / `--chart-1` |
| Notification count / unread / "New" | `--brand-accent` + `--on-accent` |
| Sidebar & tooltip chrome | `--brand-secondary` + `--on-secondary` |
| Surface / hover / border / input border | `--bg-surface` / `--bg-base` / `--border` / `--border-input` |
| Text / metadata | `--text` / `--text-muted` |
| Success · Warning · Danger · Info · Neutral | `--status-*` (soft bg + `*-fg` text) |
| Destructive action | `--action-danger` + `--on-danger` |
| Focus | `--focus-ring` (`--focus-ring-inverse` on espresso) |
| Disabled | `opacity .5` + `cursor-not-allowed` (inputs also `--bg-disabled`) |

---

_End of spec. When redesigning a component, read its entry in §6–7, apply the listed tokens for every state, check §5 for accent roles, verify AA in light AND dark, and keep org-locked tokens unchanged._
