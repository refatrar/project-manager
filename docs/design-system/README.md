# Design System — Kazsoft Project Manager

Status: **FINAL — colour and type decisions locked (UI-DS-001, 2026-09-30)**. Decisions applied: D-1 teal-700 primary, D-2 Plus Jakarta Sans. The values are specified here but not yet live in `resources/css/app.css`; each lands with its own `UI-DS-*` task. Every contrast ratio in §2 was computed with the WCAG 2.x relative-luminance formula; re-run `python3 docs/design-system/contrast-check.py` (57 pairs, exits non-zero on any failure) after changing a colour.

Derived with the UI UX Pro Max methodology (product type → reasoning profile → style → palette → typography → UX rules → anti-patterns → pre-delivery checklist). The generator classified this product as **Productivity Tool** (dashboard style: *Drill-Down Analytics*; style priority: *Flat Design / Minimalism & Swiss Style*; density 8/10). Where the generator's output was adapted, the reason is written next to it.

---

## 1. Design Philosophy

1. **Clarity over decoration.** This is a daily work tool for delivery teams. Every visual element must help someone find, decide or act.
2. **Dense but calm.** Data-dense layouts (tables, boards, grids) with generous whitespace *between* groups, not inside rows.
3. **One accent.** A single primary hue marks the primary action, focus and selection. Status colours carry meaning only — never decoration.
4. **Colour is never the only signal.** Every status, health, priority and heatmap value also has text, an icon or a pattern.
5. **Keyboard is a first-class input.** Everything reachable, focus always visible, drag always has a button alternative (already true for the board — keep it).
6. **Server is the source of truth.** Enum labels come from `options()` props (RULES.md §3/§10); UI never re-derives business rules.
7. **Flat.** Borders separate surfaces; shadows are reserved for things that float (menus, dialogs, sheets, toasts).

**Anti-patterns (banned):** emoji icons; raw Tailwind palette classes (`text-red-600`, `bg-blue-50`) in feature code; per-page colour choices; gradients, glassmorphism, neon, decorative motion; `outline-none` without a replacement focus style; placeholder-as-label; icon-only buttons without an accessible name; unconfirmed destructive actions; text smaller than 12px; dark mode by default; slow or blocking animation.

---

## 2. Color Tokens

Implemented as CSS variables in `resources/css/app.css` (`:root` and `.dark`), mapped to Tailwind with `@theme inline`. Feature code uses **only** semantic utilities (`bg-primary`, `text-muted-foreground`, `bg-success-subtle`…).

### 2.1 Light

Surfaces: canvas `#F8FAFC`, card/popover `#FFFFFF`, secondary/hover `#F1F5F9`. Minimums are 4.5:1 for text and 3:1 for control boundaries and focus indicators (WCAG 1.4.3 / 1.4.11).

| Token | Role | Value | Measured contrast |
|---|---|---|---|
| `--background` | App canvas | `#F8FAFC` | — |
| `--card` / `--popover` | Surface / elevated surface | `#FFFFFF` | — |
| `--foreground` | Text primary | `#0F172A` | 17.85 card · 17.06 canvas · 16.30 secondary |
| `--muted-foreground` | Text secondary / muted | `#475569` | 7.58 card · 7.24 canvas · 6.92 secondary |
| `--subtle-foreground` | Tertiary (timestamps, helper) | `#5F6E86` | 5.17 card · 4.94 canvas · 4.72 secondary |
| `--disabled-foreground` | Disabled text | `#94A3B8` | exempt (WCAG 1.4.3 inactive components) |
| `--primary` | Primary action, selection, links | `#0F766E` (teal-700) | as text: 5.47 card · 5.23 canvas |
| `--primary-hover` | Hover/pressed | `#115E59` | label 7.58 |
| `--primary-foreground` | Text on primary | `#FFFFFF` | 5.47 on primary |
| `--secondary` / `--accent` | Secondary button, hover surface | `#F1F5F9` | — |
| `--border` | Decorative dividers, card borders | `#E2E8F0` | decorative only (not a control boundary) |
| `--input` | Form-control boundary | `#7C8798` | 3.64 card · 3.47 canvas |
| `--ring` | Focus ring (2px + 2px offset) | `#0F766E` | 5.47 card |
| `--success` · `-foreground` · `-subtle` · `-subtle-foreground` | Done, approved, on track | `#15803D` · `#FFFFFF` · `#F0FDF4` · `#166534` | label 5.02 · as text 5.02 · subtle 6.81 |
| `--warning` · … | At risk, pending, due soon | `#B45309` · `#FFFFFF` · `#FFFBEB` · `#92400E` | label 5.02 · as text 5.02 · subtle 6.84 |
| `--destructive` · … | Errors, overdue, delete | `#B91C1C` · `#FFFFFF` · `#FEF2F2` · `#991B1B` | label 6.47 · as text 6.47 · subtle 7.60 |
| `--info` · … | Informational, in review | `#1D4ED8` · `#FFFFFF` · `#EFF6FF` · `#1E40AF` | label 6.70 · as text 6.70 · subtle 8.01 |

Changed during verification: `--subtle-foreground` was proposed as `#64748B`, but it measured 4.34:1 on the secondary surface (hovered rows, secondary buttons), below 4.5. It is darkened to `#5F6E86`, which is still lighter than muted text so the hierarchy holds. `--destructive-foreground` is now distinct from `--destructive`; in the current `app.css` they are identical.

**Kept unchanged:** `--status-good/warning/critical`, `--heatmap-1..7`, `--chart-1..5`. They were contrast-validated in earlier work (`app.css:102-136`). `--status-*` become aliases of `--success`/`--warning`/`--destructive` only if the validated values match; otherwise both sets remain.

**Adapted from the generator:** it proposed a teal-tinted canvas `#F0FDFA`, border `#99F6E4` and an orange CTA `#EA580C`. The tinted canvas and border were rejected: the border fails 3:1 (1.26:1), and a tinted canvas is loud in a data-dense app. The orange CTA was rejected because it collides with warning semantics and breaks the one-accent rule. White on the generator's `#0D9488` is only 3.74:1, so the primary is darkened one step to teal-700.

### 2.2 Dark (`.dark`)

Surfaces: background `#0A0F14`, card `#0F172A`, popover (elevated) `#1E293B`. The popover is the lightest surface, so it is the hardest case for light-on-dark text.

| Token | Value | Measured contrast |
|---|---|---|
| `--background` | `#0A0F14` | — |
| `--card` | `#0F172A` | — |
| `--popover` (elevated) | `#1E293B` | — |
| `--foreground` | `#F1F5F9` | 16.30 card · 17.56 background · 13.35 popover |
| `--muted-foreground` | `#94A3B8` | 6.96 card · 7.50 background · 5.71 popover |
| `--subtle-foreground` | `#8492A8` | 5.66 card · 6.10 background · 4.64 popover |
| `--border` | `#1E293B` | decorative only |
| `--input` | `#6B7A90` | 4.09 card · 3.35 popover |
| `--primary` · `-foreground` | `#2DD4BF` · `#042F2E` | label 7.77 · as text 9.59 card · 7.86 popover |
| `--primary-hover` | `#5EEAD4` | label 9.78 |
| `--ring` | `#2DD4BF` | 9.59 card |
| `--success` · `-foreground` | `#4ADE80` · `#052E16` | label 8.55 · as text 10.25 |
| `--warning` · `-foreground` | `#FBBF24` · `#451A03` | label 8.97 · as text 10.69 |
| `--destructive` · `-foreground` | `#F87171` · `#450A0A` | label 5.84 · as text 6.45 |
| `--info` · `-foreground` | `#60A5FA` · `#172554` | label 5.78 · as text 7.02 |
| `--{tone}-subtle` | the tone at 15% alpha over card: success `#183537`, warning `#323029`, destructive `#322435`, info `#1B2C49` | tone text on it: 7.52 · 7.91 · 5.27 · 5.50 |
| `--{tone}-subtle-foreground` | the tone itself (e.g. `#4ADE80`) | as above |
| `--sidebar-primary` · `-foreground` | `#2DD4BF` · `#042F2E` | 7.77; replaces the current bug where both values are identical (`app.css:176-177`) |

Added or changed during verification: `--subtle-foreground` and `--primary-hover` had no dark values; they are now `#8492A8` and `#5EEAD4`. `--input` was lightened from `#64748B` (3.07:1 on popover, too close to the limit) to `#6B7A90` (3.35:1).

The generator does not produce dark tokens; these are derived and pass the same thresholds as light. `resources/views/app.blade.php` inline background colours must be updated to match, so there is no flash on load (UI-DS-003).

### 2.3 Status mapping (single source: `resources/js/lib/status.ts`)

| Domain value | Tone | Icon (lucide) |
|---|---|---|
| Task `done`, milestone/allocation `completed`, approval `approved`, health `on_track` | success | `CircleCheck` |
| `in_progress`, `in_review`, `ready_for_qa`, `submitted` | info | `CircleDot` |
| health `at_risk`, approval `pending`, due within 48h | warning | `TriangleAlert` |
| health `off_track`, overdue, `rejected`, `blocked` | destructive | `CircleAlert` |
| `cancelled`, `archived`, `draft`, `todo`/`backlog` | neutral | `CircleSlash` / `Circle` |

The exact enum → tone table is finalised in UI-CMP-002 from `app/Enums/*`. It is not invented here. `cancelled` is deliberately neutral: it is currently red everywhere, which reads as an error.

---

## 3. Typography

- **Family:** **Plus Jakarta Sans**, weights 400/500/600/700 (decision D-2; the generator's pick for SaaS/productivity). It replaces Instrument Sans (`app.blade.php:37`) in UI-DS-004. Load it through fonts.bunny.net as today, with `font-display: swap`.
- **Numbers:** `tabular-nums` on every table cell, KPI, duration, date and hour value.

| Role | Size / line-height | Weight | Tailwind |
|---|---|---|---|
| Page title (`h1`, one per page) | 24/32 (20/28 < 768px) | 600 | `text-xl md:text-2xl font-semibold` |
| Section title (`h2`) | 18/28 | 600 | `text-lg font-semibold` |
| Card title (`h3`) | 16/24 | 600 | `text-base font-semibold` |
| Body (app default) | 14/20 | 400 | `text-sm` |
| Reading body (descriptions, minutes) | 16/24, max 72ch | 400 | `text-base` |
| Label | 14/20 | 500 | `text-sm font-medium` |
| Helper / meta / table header | 12/16 | 400–500 | `text-xs` |
| Error text | 12/16 | 500 | `text-xs font-medium text-destructive` |
| KPI number | 24/32 | 600 | `text-2xl font-semibold tabular-nums` |

Rules: 12px minimum (removes the `text-[10px]`/`text-[11px]` in burndown); inputs render at 16px below 768px to avoid iOS zoom (shadcn `text-base md:text-sm` already does this); long text wraps, and truncated text shows its full value in a tooltip/`title`.

---

## 4. Spacing, Radius, Elevation, Z-index

**Spacing** uses a 4px base and Tailwind's scale only; no arbitrary pixel values.

| Context | Value |
|---|---|
| Page padding | 16px < 768 · 24px ≥ 768 · 32px ≥ 1440 (`p-4 md:p-6 2xl:p-8`) |
| Section gap | 24px (`gap-6`) |
| Card padding | 16px compact (lists, KPI) · 24px default (forms, detail) |
| Form field stack | 16px; label → control 8px; control → helper/error 6px |
| Table cell | 12px × 8px, row height 40px (36px compact) |
| Dialog | 24px padding; footer separated by border |
| Drawer / sheet | 24px; 16px < 768 |
| Inline icon → text | 8px (`gap-2`) |

**Radius:** `--radius: 0.5rem` (currently 0.625rem). Scale: sm 4px (checkbox, badge), md 6px (button, input), lg 8px (card, dialog, popover), full (avatar, pill).

**Elevation:** level 0 is border only (cards, tables). Level 1 is sticky elements: border + `bg-background/95`. Level 2 is dropdown, popover and tooltip (`shadow-md`). Level 3 is dialog, sheet and toast (`shadow-lg`). The dialog overlay uses a token `--overlay` (currently hardcoded `bg-black/80`).

**Z-index:** content 0 · sticky table headers/columns 10 · app header 20 · sidebar 30 · Radix portals (menus, dialogs) 50 · toasts 100.

---

## 5. Icons

- Library: **lucide-react** only (already used; named imports tree-shake). No emoji as icons.
- Sizes: 16px inline/in buttons (`size-4`), 20px empty-state/KPI (`size-5`), 32px empty-state hero (`size-8`). Default stroke (2).
- Decorative icons: `aria-hidden="true"`. Icon-only buttons: `aria-label` (required by the `IconButton` type) + tooltip with the same text.
- One glyph = one meaning. For example, `ArrowUpRight` currently means both "open task" and "promote to task" in `todo-list-card.tsx`. Promote becomes `ListPlus`.

---

## 6. Buttons

| Variant | Use |
|---|---|
| `default` (primary) | The one main action per view/dialog |
| `secondary` | Alternative actions |
| `outline` | Toolbar/filter controls, Cancel |
| `ghost` | Row actions, icon buttons |
| `destructive` | Confirming a destructive action (inside a confirm dialog) |
| `link` | Inline navigation |

- **Sizes:** sm 32px, default 36px, lg 40px, icon 36px. On `pointer-coarse`, small and icon buttons grow to ≥40px. Web minimum is 24×24 (WCAG 2.2 AA), and targets are 8px apart.
- **Props:** `loading` shows a spinner, sets `aria-busy`, disables the button and keeps its width.
- **States:** hover changes colour only (120ms). The focus ring is visible. Pressed has no layout shift. Disabled is `opacity-50` plus `cursor-not-allowed`, and never used as the only explanation. Where an action is disabled for a reason, a helper text or tooltip gives it (e.g. timesheet "Submit week").

## 7. Forms

- **`FormField`** composite: `Label` (+ required marker `*` and `aria-required`), control, optional hint, `InputError`. It wires `id`, `aria-describedby` and `aria-invalid` automatically. Today there are 0 `aria-invalid` and 1 `aria-describedby` in the whole app.
- Labels are always visible, above the control. Placeholders are examples, not labels.
- Group long forms into titled sections (the `project-form.tsx` sections are the reference pattern).
- Errors: inline below the field (from `useHttp` 422 `errors`). Non-field failures (403/500/network) show a form-level `Alert` at the top of the dialog. After a failed submit, focus moves to the first invalid field.
- Optional Radix selects keep the server sentinel `'none'` (MEMORY.md): do not change the value contract.
- Dates: native `date`/`datetime-local`/`time` inputs stay (29 in use); decision D-3 deferred a date-picker dependency. Keep the `Date.UTC` rule and seconds in `datetime-local` pre-fill (MEMORY.md).
- Two-column field grids only at ≥640px (`sm:grid-cols-2`). Dialog footer: Cancel (outline) then primary; stacked full-width on mobile.

## 8. Tables

- Primitive: `ui/table.tsx` (shadcn). Composite: `DataTable` (column config, optional client sort for fully loaded data, loading skeleton rows, `EmptyState`, row actions menu, responsive card mode).
- Semantics: `<table>`, `<caption>` (can be `sr-only`), `<th scope="col">`, and `aria-sort` on sortable headers.
- Density: 40px rows, 12px horizontal padding, numbers right-aligned with tabular figures, sticky header inside scroll containers, sticky first column for wide grids (timesheet, heatmap).
- Responsive: ≥768px renders the table inside `overflow-x-auto`, and the page itself never scrolls horizontally. <768px switches to stacked cards (primary field as title, 2–4 secondary fields, actions menu) for list tables. Grid tables (timesheet, heatmap) keep horizontal scroll with a sticky first column.
- Sorting is **server-side** for paginated lists only after backend task UI-BE-003. Until then only fully loaded, client-side datasets (task list, dashboard projects, capacity members) are sortable.
- Pagination: one `Pagination` component that replaces the 7 copies. It uses `aria-label="Pagination"` and `aria-current="page"`, has no `dangerouslySetInnerHTML`, and passes `preserveScroll`.

## 9. Cards

Border, radius lg, `bg-card`, no shadow. A `StatCard` has a label, value and optional delta/trend, plus an optional `href` that makes the whole card a link with a focus ring. Titles use h3 (`CardTitle` renders an element chosen by context).

## 10. Dialogs

- `Dialog` is used for create/edit forms (ADR-013: the workspace never navigates away for CRUD).
- Content: `max-h-[min(90dvh,48rem)]`, a scrollable body, and a sticky header and footer. This generalises `project-form-modal.tsx:63` to all dialogs.
- Widths: sm 28rem (confirmations, small forms), md 36rem, lg 42rem (task, project, meeting forms).
- <640px: full width and edge-aligned (bottom-sheet feel), same component.
- **`ConfirmDialog`** (Radix `AlertDialog`) handles every destructive action. It states the object name and the consequence, and uses a destructive primary button. Esc/overlay click cancels (never confirms). It has `onError` handling (currently 0 of 12 delete modals handle errors). Typed-name confirmation stays for team delete.

## 11. Drawers / Sheets

`ui/sheet.tsx` (exists). Used for mobile navigation (already), mobile filter panels, and the optional task quick-preview (UI-TASK-010). Width 24–28rem on desktop; full width on mobile.

## 12. Navigation

- App sidebar (`ui/sidebar.tsx`, collapsible to icons ≥768px, sheet <768px). Items have an icon and a text label. The active state matches the current URL **or a parent URL** (fixes detail pages losing highlight), with `aria-current="page"`.
- Header: sidebar trigger, breadcrumbs (collapse middle crumbs <768px), then a right slot for search/command trigger (if D-3 approves `cmdk`).
- Breadcrumbs go at least 3 levels deep: Projects › ALPHA › ALPHA-114.
- A skip link reads "Skip to main content", and focus moves to the page `h1` after an Inertia navigation.
- Admin uses the **same** sidebar primitives with its own item list (it currently has a top bar with no mobile handling).

## 13. Tabs

Radix Tabs (dependency D-3). Keyboard arrows, `aria-controls`, and a horizontally scrollable list with fade edges. The selected tab is reflected in the URL (`?tab=`), so refresh/back/links work. <640px: the tab list may collapse into a `Select`.

## 14. Badges

Variants: `neutral`, `success`, `warning`, `destructive`, `info`, `outline`; styles `subtle` (default: tinted background with dark text) and `solid`. Always contains text. `StatusBadge`, `HealthBadge` and `PriorityBadge` wrap it with the mapping from §2.3 and labels from server options. Label chips use the label's colour as a 10px dot plus neutral text, never as the background. User-chosen colours are not contrast-safe (`task-form.tsx:335-356`).

## 15. Alerts & Toasts

- **Alert:** `info`, `success`, `warning`, `destructive` variants (currently only default/destructive; `team-invitation-alert.tsx` hand-rolls blue). Placed above the content it concerns.
- **Toasts** (sonner, exists): success auto-dismisses after 4s; errors persist until dismissed. They never steal focus and are announced politely. Every `useHttp` success shows `response.message` (several admin callers drop it today). Failures show a toast *and* keep inline errors. Position: bottom-right on desktop, bottom-centre on mobile.

## 16. Loading

- Page navigation: the Inertia progress bar in `--primary` (currently hardcoded `#4B5563`).
- Partial reloads (`router.reload({only})`, 37 call sites) show a subtle pending state on the affected region (`aria-busy`, 60% opacity after a 200ms delay to avoid flicker).
- Buttons use `loading` during `form.processing`.
- Skeletons are only for first-paint deferred content (after UI-BE-004), sized to the final layout (CLS < 0.1).

## 17. Empty States

`EmptyState` has an icon, title, one-sentence explanation and a primary action (only if the user has permission). It distinguishes *no data yet* from *no results for these filters*; the latter gets a "Clear filters" button. It uses product terminology, for example:

> **No projects yet** — Create a project to organise tasks, members and milestones. [New project]
>
> **No projects match these filters** — Try removing a filter. [Clear filters]

## 18. Error States

- Form: inline + form-level alert (§7).
- Mutation failure (delete, toggle, move): error toast with a plain-language message; the item stays in place.
- Page-level: Inertia error pages for 403/404/419/500/503 rendered in the app shell (UI-CMP-017). They say what happened, what to do, and whether retrying helps. They never show stack traces or SQL.
- Authorization: hide actions the user cannot perform (existing `can` props). If a server 403 still occurs, show "You don't have permission to do this. Ask a team lead." Do not show raw messages.

## 19. Responsive Rules

- Breakpoints: 375 (min supported), 640 `sm`, 768 `md`, 1024 `lg`, 1280 `xl`, 1440 `2xl`, 1920 (content caps at `max-w-screen-2xl` for reading pages; data pages stay fluid).
- No page-level horizontal scroll at any width (the current `overflow-x-clip` on `SidebarInset` hides overflow instead of fixing it).
- Header rows (`title + actions`) wrap. Primary actions stay visible and secondary actions move into a menu below 640px.
- Filters: inline ≥1024px; a "Filters (n)" button opening a sheet below that. Active filter chips always visible.
- Fixed widths (`w-40`, `w-56`, `w-72`) become `w-full sm:w-40`, etc.
- Touch: ≥40px targets on coarse pointers; nothing relies on hover (burndown and heatmap tooltips must also open on focus/tap).

## 20. Accessibility Rules (WCAG 2.2 AA target)

Contrast 4.5:1 text / 3:1 large text and UI boundaries in both themes. Visible focus (2px ring, offset 2px), never obscured by sticky UI. Logical tab order (no positive `tabIndex`, currently on both login pages). One `h1` per page, sequential headings. Labels for every control (checkboxes currently unlabelled in to-do, agenda, action items). `aria-live="polite"` region for async results ("12 tasks", "Saved"). Dialog focus trap and return focus (Radix provides it; keep it). Drag has a button alternative (board chevrons + status select; keep them if DnD is ever added). Charts have a text or table alternative. Heatmap cells focusable. Status never colour-only.

## 21. Motion Rules

| Token | Duration | Use |
|---|---|---|
| `--motion-fast` | 120ms | colour/opacity hover, focus |
| `--motion-base` | 180ms | popover, dropdown, accordion, tab indicator |
| `--motion-slow` | 240ms | dialog, sheet enter |
| exit | ~70% of enter | |

Ease-out on enter, ease-in on exit. Animate `opacity` and `transform` only, with at most 1–2 animated elements per interaction. Nothing loops except spinners. Under `prefers-reduced-motion: reduce`, transform animations are removed and opacity fades are capped at 100ms (global rule in `app.css`). The generator's GSAP/scroll-reveal suggestions are marketing-page patterns and are **not** used.

---

## 22. Pre-delivery checklist (every UI task)

- [ ] No emoji icons; lucide only; decorative icons `aria-hidden`
- [ ] Semantic tokens only — no raw palette classes or hex in feature code
- [ ] `cursor-pointer` + hover state (120–180ms) on every clickable element
- [ ] Visible focus on every interactive element; tab order = visual order
- [ ] Contrast checked in **light and dark** (text 4.5:1, UI 3:1)
- [ ] `prefers-reduced-motion` respected
- [ ] 375 / 768 / 1024 / 1440 checked; no horizontal page scroll
- [ ] Touch targets ≥24px (≥40px on coarse pointer), 8px apart
- [ ] Loading, empty (no data / no results), error and disabled states present
- [ ] Destructive actions confirmed; failed mutations reported
- [ ] Icon-only controls named; form fields labelled with linked errors
- [ ] Permission-gated actions still gated (compare with the `can*` props)
- [ ] No new request per render; `only: [...]` reload keys unchanged
