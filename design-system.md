# Kern Admin Design System Reference

> For Next.js projects using Tailwind CSS and shadcn/ui that want to adopt the Kern CMS admin UI and UX.
> Reviewed on 19 October 2026 at commit `7095745`.
> The review covered stylesheets, components, layouts, and representative screens in the source code. Browser verification, screenshots of the running UI, and hands-on accessibility testing are pending.

## 1. How to use this document

This document is independent of the backend and can be copied as-is into a new project. The specifications below are sufficient to recreate the default look and feel independently of Kern CMS's Laravel, Inertia, session handling, and module structure.

The document uses the following labels.

- **Current implementation** describes values or behavior in the code reviewed. This includes the specification tables and descriptions of default components.
- **Design interpretation** summarizes patterns inferred from existing components. The repository has no explicit set of design principles covering these patterns.
- **Next.js recommendation** identifies ways to organize or extend the design in the target project.

This reference covers the admin shell, themes, UI primitives, data lists, content forms, settings, dashboard, global search, action feedback, media picker, and authentication. It covers selected screens and plugins.

Pixel conversions assume `1rem = 16px` and Tailwind's default spacing and breakpoints, which the admin stylesheet uses as-is. If your project customizes these scales, check the resolved values for the classes.

## 2. Visual direction

**Design interpretation.** A compact admin interface with a clear hierarchy and moderate information density. The background is cool gray, cards are lighter than the background, and a subdued blue provides the accent. Dark mode uses charcoal gray and pale blue.

Preserve the following characteristics.

1. Separate regions with background colors and 1px borders. Leave standard cards flat.
2. Use the primary color for primary actions, links, active states, and focus.
3. Give page headings moderate emphasis. Keep card headings small, bold, and close to the data they describe.
4. Use small corner radii on buttons, inputs, and navigation items. Keep status badges compact.
5. Repeat the same search, filter, table, and pagination structure across list pages.
6. Include loading, validation, and notifications in interaction flows.
7. Stack content in a single column and increase control sizes on small screens.

To preserve this style, avoid shell gradients, blur or glass effects, large shadows on every card, overly rounded cards, oversized headings, and separate palettes for each module. The authentication page sits outside the admin shell and has its own background image with a gradient overlay.

The repository's shadcn/ui configuration uses `style: new-york` and `baseColor: zinc`. Apply the custom palette and component styles as well as the preset to reproduce the interface in a new project.

## 3. Colors and themes

### 3.1. Default palette

| CSS token | Light | Dark | Role |
| --- | --- | --- | --- |
| `--background` | `#f6f7f9` | `#171a1f` | Page background |
| `--foreground` | `#20252b` | `#e6ebf1` | Main text |
| `--card` | `#ffffff` | `#20252c` | Card, header, dialog |
| `--sidebar` | `#eef1f5` | `#1b2027` | Navigation background |
| `--primary` | `#214f7d` | `#95bce5` | Primary action background and emphasis |
| `--primary-ink` | `#214f7d` | `#95bce5` | Primary-colored text and icons |
| `--primary-foreground` | `#ffffff` | `#171a1f` | Text on the primary background |
| `--secondary` | `#e9edf2` | `#2d3540` | Secondary actions and neutral backgrounds |
| `--secondary-foreground` | `#354252` | `#d2dae4` | Text on the secondary background |
| `--accent` | `#e4eaf1` | `#2a3441` | Hover, active navigation |
| `--muted` | `#eef1f4` | `#282f38` | Secondary surfaces and table headers |
| `--muted-foreground` | `#5d6672` | `#a5afbd` | Descriptions and metadata |
| `--border` | `#d9dfe6` | `#35404e` | Card borders and dividers |
| `--input` | `#7c8998` | `#728297` | Control border with more emphasis than the card border |
| `--ring` | `#214f7d` | `#95bce5` | Focus |
| `--destructive` | `#b91c1c` | `#fc9999` | Errors and destructive content |

The `card-foreground`, `popover-foreground`, `accent-foreground`, and `sidebar-foreground` utilities use `--foreground`. The `popover` utility uses `--card`.

Common color combinations include the following.

- Table headers use `bg-muted/40`. Hovered table rows use `bg-muted/30`.
- Active sidebar items use `bg-accent text-foreground` and a primary-colored indicator.
- Count labels use `bg-primary/10 text-primary-ink`.
- Media placeholders, notices, and icon containers typically use `bg-muted/30`.
- Dialog and mobile navigation overlays use black at 45% opacity.

### 3.2. Status colors

| Badge | Light | Dark |
| --- | --- | --- |
| Neutral | `bg-secondary text-secondary-foreground border-transparent` | Uses theme tokens |
| Success | `border-emerald-200/70 bg-emerald-50 text-emerald-800` | `border-emerald-800 bg-emerald-950/60 text-emerald-300` |
| Warning | `border-amber-200/70 bg-amber-50 text-amber-900` | `border-amber-800 bg-amber-950/60 text-amber-200` |
| Destructive | `border-red-200 bg-red-50 text-destructive` | `border-red-900 bg-red-950/50 text-destructive` |
| Outline | `border-border text-muted-foreground` | Uses theme tokens |

The destructive button uses `bg-red-700 text-white hover:bg-red-800` in both themes. Preserve that background. In dark mode, `--destructive` is a light color intended for error text.

### 3.3. Theme and branding customization

**Current implementation**

- Three options are available, `light`, `dark`, and `system`. The toggle cycles through Light → Dark → System → Light.
- The root element uses the `.dark` class for dark mode. The theme follows the operating system by default.
- A preference saved in the browser takes precedence over the account's default theme.
- System mode follows operating system theme changes. The theme selection syncs across tabs.
- You can configure the logo, logo height, favicon, font, primary color, some text and link colors, and content width.
- Primary color customization uses `primary` for backgrounds and `primary-ink` for text. The color helper enforces a minimum text contrast ratio of 4.5 against three predefined surfaces or uses a fallback color. This check covers those surfaces only.
- Some overrides, including secondary, heading, text, and link colors, apply only in light mode.

The table lists **the defaults in the source code**. Values configured in the database may differ. The font can also be loaded from a configured URL.

**Next.js recommendation.** Keep the Light, Dark, and System options. Apply the theme before the first paint using a mechanism appropriate for the project to avoid theme flashes and hydration errors. Keep branding customization if the target product needs it.

## 4. Typography, spacing, and shapes

### 4.1. Typography

The default font is a system sans-serif stack.

```css
font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont,
  "Segoe UI", sans-serif;
```

`--admin-font-family` can override this stack. The admin has no requirement for Inter or a commercial font.

| Role | Typical class | Resolved values |
| --- | --- | --- |
| Page title | `text-2xl font-semibold tracking-tight` | 24px / line-height 32px / 600 |
| Dialog title | `text-lg font-semibold tracking-tight` | 18px / 28px / 600 |
| Settings group title | `text-base font-semibold` | 16px / 24px / 600 |
| Card title | `text-sm font-semibold tracking-tight` | 14px / 20px / 600 |
| Field label | `text-sm font-medium leading-5` | 14px / 20px / 500 |
| Table, menu, and button text | `text-sm` | 14px / 20px |
| Page or card description | `text-sm leading-relaxed text-muted-foreground` | 14px / line-height about 22.75px |
| Helper text, field errors, and badges | `text-xs` | 12px / 16px. Helper text may use `leading-relaxed`. |
| Input, select, and textarea text | `text-base sm:text-sm` | 16px below 640px, 14px from 640px |
| Dashboard metrics | `text-2xl font-semibold tabular-nums` | 24px / 600 with equal-width digits |

Use `truncate` only where width is genuinely constrained, such as breadcrumbs, account names, and selected values. Allow form content, errors, and dialog titles to wrap. Grid regions typically use `min-w-0` to prevent overflow.

### 4.2. Spacing

These spacing values recur throughout the UI. The repository uses them without declaring a separate set of spacing tokens.

| Spacing | Class | Use |
| --- | --- | --- |
| 4px | `gap-1`, `space-y-1` | Closely related details |
| 6px | `gap-1.5`, `space-y-1.5` | Card titles and descriptions, badge icons |
| 8px | `gap-2`, `space-y-2` | Labels and controls, button groups |
| 12px | `gap-3`, `space-y-3` | Toolbars, icons and text, control groups |
| 16px | `gap-4`, `p-4` | Toolbars and compact data regions |
| 20px | `gap-5`, `space-y-5`, `p-5` | Spacing between cards and fields, card padding |
| 24px | `gap-6`, `space-y-6`, `p-6` | Desktop dialogs and large form groups |
| 32px | `space-y-8` | Settings groups and authentication layouts |

### 4.3. Borders, radii, shadows, and icons

- Standard borders are 1px and use `border-border`. Inputs use `border-input`.
- `rounded-sm` is about 4px, `rounded-md` about 6px, and `rounded-lg` about 8px. Cards and dialogs use 8px. Buttons, inputs, and badges use 6px. Avatars and count badges can be fully rounded.
- Cards are flat by default. Dropdowns and selects use `shadow-md`, dialogs and mobile drawers use `shadow-xl`, and toasts use `shadow-lg`.
- The UI uses Lucide icons. Buttons and menus typically use 16px icons, the sidebar uses 18px, settings icon containers use 20px icons, dashboard metrics use 24px, and list empty states use 32px.
- Icons typically sit 8–12px from accompanying text. Decorative icons use `aria-hidden`, and icon-only buttons need an accessible name.

**Next.js recommendation.** Check the project's shadcn/ui radius defaults. If cards or buttons have larger radii than specified above, adjust the shared component or theme so the change applies across pages.

## 5. Admin shell and responsive layout

### 5.1. Main layout

```text
┌───────────────┬─────────────────────────────────────────────┐
│ Logo / brand  │ Header: breadcrumb   search/theme/user      │
│               ├─────────────────────────────────────────────┤
│ Sidebar       │ H1 + description              Page actions  │
│ Menu groups   │                                             │
│               │ Content: table / card / form / dashboard    │
│               │                                             │
└───────────────┴─────────────────────────────────────────────┘
```

| Element | Current implementation |
| --- | --- |
| Desktop sidebar | Fixed, full viewport height, 256px wide (`w-64`) |
| Collapsed sidebar | 72px wide (`w-18`), with 18px icons in 44px buttons |
| Desktop sidebar breakpoint | From `md` = 768px |
| Header | Sticky at the top, 64px tall (`h-16`), `border-b bg-card` |
| Content offset | From 768px, the inline-start offset equals the current sidebar width |
| Default main area | Centered in the area beyond the sidebar, `max-w-[1440px]` |
| Main padding | 16px. From 640px, 24px horizontally and 20px vertically. From 1024px, 32px horizontally. |
| Header padding | 16px horizontally → 24px at `sm` → 32px at `lg` |
| Heading row | `mb-5 flex flex-wrap items-start justify-between gap-4` |
| Page actions | `flex shrink-0 items-center gap-2` |
| Description below H1 | `mt-1.5 max-w-2xl text-sm leading-relaxed text-muted-foreground` |

The available container options are `container-xl` = 1320px, `container-3xl` = 1320px increasing to 1600px from a 1920px viewport, and `container-fluid` for an uncapped width. These widths apply to the main area alone, outside the sidebar.

The main area scrolls with the page. The sidebar has an independent scroll region with a thin scrollbar and `overscroll-contain`. The header spans the full area beyond the sidebar, outside the width-constrained main area.

### 5.2. Navigation

- Regular items use `min-h-11 sm:min-h-9 rounded-md px-3 py-2 text-sm` and 12px between icon and text.
- Regular items use muted text. Hover applies `bg-accent text-foreground`.
- Active items use `bg-accent font-medium text-foreground`, a 2px primary-colored indicator at the start edge, and `aria-current="page"`.
- Submenus have a 20px indent, a vertical border, and 12px of inline-start padding (`ms-5 border-s ps-3`).
- The group containing the active page expands automatically. The browser stores the expanded state of groups and the collapsed state of the sidebar.
- The session preserves sidebar scroll positions separately for compact and expanded modes.
- In compact mode, groups with submenus open a dropdown. Each icon has a label or title.
- The desktop breadcrumb starts with Dashboard and leads to the current page title. Multi-level breadcrumbs are outside the default pattern for resources.

### 5.3. Small screens

| Breakpoint | Changes |
| --- | --- |
| Below 400px | The header reduces horizontal padding to 8px and uses smaller gaps |
| Below 640px | Controls are typically 44px tall, and inputs and selects use 16px text |
| From 640px (`sm`) | Controls become more compact, and settings items can use two columns |
| Below 768px | The desktop sidebar and breadcrumbs are hidden. The hamburger button opens navigation. |
| From 768px (`md`) | The sidebar is fixed and the main area is offset accordingly |
| From 1024px (`lg`) | Primary and secondary form areas and settings sections move to two columns |
| Below 1280px | Many header buttons use a 44px size and hide secondary labels or shortcuts |
| From 1280px (`xl`) | Dashboard widgets can use two columns, and the account name is visible |

Mobile navigation is a dialog styled as a drawer, with a width of `min(320px, 100vw - 48px)`, a black overlay at 45% opacity, a close button, and focus restoration on close. Selecting a link closes the drawer, as does switching to desktop.

**Next.js recommendation.** Prevent horizontal overflow at the page level. Allow local horizontal scrolling for tables or content regions that need it. Test at 375px, 640px, 768px, 1024px, and 1440px. Also test 1920px if you use the wider container.

## 6. Component specifications

### 6.1. Button

The base classes are `inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md border text-sm font-medium`. Transitions apply only to background color, text color, border color, and shadow. The default icon size is 16px.

| Variant | Main color classes | Role |
| --- | --- | --- |
| Default | `bg-primary text-primary-foreground hover:bg-primary/90` | Create, save, continue |
| Secondary | `bg-secondary text-secondary-foreground hover:bg-secondary/75` | Secondary actions |
| Outline | `border-input bg-card hover:bg-accent hover:text-accent-foreground` | Back, cancel, search, save and continue |
| Ghost | `hover:bg-accent hover:text-accent-foreground` | Toolbar icons and low-emphasis actions |
| Destructive | `bg-red-700 text-white hover:bg-red-800` | Confirm deletion |
| Link | `text-primary-ink underline-offset-4 hover:underline` | Link-style actions |

| Size | Below 640px | From 640px | Horizontal padding |
| --- | --- | --- | --- |
| Default | 44px | 36px | 16px |
| Small | 44px | 32px | 12px, with 12px text |
| Large | 48px | 40px | 24px |
| Icon | 44 × 44px | 36 × 36px | Determined by size |

Disabled buttons use 50% opacity and block interaction. Loading retains the label, adds a spinner, blocks interaction, and sets `aria-busy`. Focus-visible uses a 3px ring at 25% opacity and a ring-colored border. The source component defaults to `type="button"`, so declare the submit type explicitly for submit buttons.

**Design interpretation.** Make one action in a group clearly primary. Use outline or ghost for the other actions according to their roles.

### 6.2. Input, textarea, and field

- Inputs and selects use `h-11 sm:h-9 px-3`, a card background, the input border, and a 6px radius.
- Textareas use `min-h-28` (112px), `resize-y px-3 py-2.5`, and relaxed line height.
- Placeholder: `text-muted-foreground/80`.
- Focus: `focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/20`.
- Invalid: `aria-invalid:border-destructive aria-invalid:focus-visible:ring-destructive/20`.
- Disabled: `disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-60`.
- Readonly: `bg-muted/50`.
- Fields use `space-y-2` and contain the label above the control, followed by a description and an error when present.
- The label is linked to the control by ID. The red `*` serves as a visual indicator. Set `required` on the control where applicable.
- Helper text is muted and 12px. Errors use 12px medium-weight destructive text and `role="alert"`. The control references helper and error text with `aria-describedby` and sets `aria-invalid` when invalid.
- Related fields can use `fieldset` and `legend`.

Search inputs use a leading 16px icon and `ps-9`. Reserve `pe-11` when a clear action is available. Clearing returns focus to the input. List search and global search submit differently, as described in sections 7 and 8.

### 6.3. Select, checkbox, and radio

- Single select uses Radix Select and shares the input style. The menu is at least as wide as the trigger, sits 4px away, and has a border and medium shadow.
- Select opens a search dialog when `searchable` is enabled, when multiple selections are allowed, or automatically when there are at least 10 options and search is enabled.
- The search dialog supports single and multiple selection. Single selection closes the dialog after a choice. Multiple selection shows the selected count and a Done button.
- A separate NativeSelect is available for contexts that require a native listbox.
- Checkbox and radio indicators are 18px. The wrapper and pseudo-element expand the interaction area to 44px on mobile and 28px from `sm`.
- Checkboxes support an indeterminate state for partial selection. Checked controls use the primary color. Radio buttons have an inner dot.

**Next.js recommendation.** Use Select for short option lists. For long lists, implement an equivalent search pattern with a suitable existing component. Preserve labels, keyboard selection, and focus management when replacing the primitive.

### 6.4. Card and FormSection

| Part | Reference classes |
| --- | --- |
| Card | `min-w-0 rounded-lg border bg-card text-card-foreground` |
| Header | `space-y-1.5 border-b px-5 py-3` |
| Title | `text-sm font-semibold tracking-tight` |
| Description | `text-sm leading-relaxed text-muted-foreground` |
| Content | `p-5` |
| Footer | `flex items-center gap-3 border-t px-5 py-3` |
| FormSection content | `space-y-5` inside CardContent |

Some screens use a `p-5` card with an internal divider in place of a separate CardHeader. Keep the same padding and text hierarchy in this variant. FormSection connects its heading to the section with `aria-labelledby`.

### 6.5. Data tables

- The table uses `w-full text-sm` and sits inside a local horizontal scroll region when needed.
- The header uses `bg-muted/40 text-xs text-muted-foreground`, and `th` uses medium font weight.
- Cells use `px-4 py-3 align-middle` (16px horizontally and 12px vertically).
- Rows use `border-b last:border-0 hover:bg-muted/30`.
- The leading checkbox has its own touch area and uses an indeterminate state for partial selection.
- Sortable headers are buttons. The sorted column shows the ↑/↓ direction and the corresponding `aria-sort` value.
- The resource name links to the content. Metadata and dates use muted text, and IDs use `tabular-nums`.
- A success badge marks published items. Other states can use outline badges. Map statuses to the new project's business rules.
- Place less frequent actions, such as edit and delete, in an ellipsis menu with a clear accessible name.

### 6.6. Dialog and dropdown

| Dialog property | Current implementation |
| --- | --- |
| Width | `calc(100% - 2rem)`, leaving 16px on each side |
| Size `sm` / `lg` / `xl` / `full` | Max 448 / 768 / 1024 / 1600px |
| Default size | `lg` |
| Height | Max `calc(100dvh - 2rem)` |
| Surface | `rounded-lg border bg-card shadow-xl` |
| Header | `p-5`, changing to `px-6 py-4` from `sm`, with a bottom border |
| Body | `p-5 sm:p-6`, independent vertical scrolling, `min-h-0 overscroll-contain` |
| Footer | Top border, `px-5 py-3 sm:px-6`, wrapping actions aligned to the end |

Dialogs have a title, an optional description, an X button, initial focus management, and focus restoration to the trigger. You can specify the initial focus target. By default, the component looks for a suitable input field. Nested dialogs receive progressively higher layers.

Dropdowns use `min-w-48` (192px) and `rounded-lg border bg-popover p-1.5 shadow-md`. They sit 6px from the trigger and maintain an 8px clearance from the viewport edges. Items use `min-h-11 sm:min-h-9 rounded-md px-3 py-2 text-sm` with an accent background on hover or highlight. Destructive items use red text and a light red background when highlighted.

For dialogs with long content, make the body scroll independently and keep the header and footer visible in the viewport.

## 7. Page patterns and workflows

### 7.1. List page

```text
H1: Resource name                                  [+ New]
┌──────────────────────────────────────────────────────────┐
│ [Bulk actions when rows are selected]                    │
├──────────────────────────────────────────────────────────┤
│ Search    Status    [Search] [Advanced filters]   Tools  │
├──────────────────────────────────────────────────────────┤
│ Checkbox │ Name │ Status │ Created at │ ...              │
│ ...                                                      │
├──────────────────────────────────────────────────────────┤
│ Total results / Rows per page     [Previous] 1/N [Next]  │
└──────────────────────────────────────────────────────────┘
```

**Current implementation**

1. The create action appears in the page actions beside the title when the user has permission.
2. The bulk action bar appears only when rows are selected and the resource supports bulk actions.
3. The toolbar uses `flex flex-wrap items-center gap-3 border-b p-4`. Search can grow and has a minimum width of 192px. The status filter has a minimum width of 144px. Table tools align to the end when space allows.
4. Apply list search by submitting with Enter or the Search button. Status changes apply immediately. The input's clear action only empties the field. Reset the filters to clear the applied criteria.
5. Changing search, filters, sort, or page size returns the list to page 1. The URL carries the applied criteria, and table navigation preserves scroll position.
6. During loading, the toolbar shows a spinner in a reserved area, disables the relevant controls, and sets `aria-busy` on the card.
7. Select all selects the rows on the current page. Changes to the data, page, or filters clear the selection to prevent unintended actions.
8. Deleting one or more items opens a confirmation dialog. The confirm button uses the destructive variant and shows a loading state. The dialog stays open while the deletion is in progress.
9. The empty state stays inside the card with a 32px Files icon, “No results found” text, `p-12`, and centered alignment.
10. The footer uses `px-4 py-3` and wraps on narrow screens. It shows the total result count, rows per page, Previous and Next controls, and page numbers. The default is 20 rows. Standard options are 10/20/50/100, and the current valid value can be included as an additional option.

### 7.2. Shared content form

- The grid uses `grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_300px]`.
- The main column holds content groups and uses `min-w-0 space-y-5`.
- The secondary column holds status, images, categorization, and save actions according to the schema.
- The save action card can use `lg:sticky lg:top-24` (96px).
- The form offers Save and Save and continue. Only the active button shows loading, and the whole group is disabled during submission.
- The form shows an “Unsaved changes” state, inline field errors, and an error summary in the action card when errors occur.
- Back appears in the page actions and uses the outline variant.
- The form warns before leaving with unsaved changes. A successful save updates the baseline used to detect changes.

**Next.js recommendation.** Use explicit labels such as Save & return and Save & continue editing if the two buttons lead to different destinations. Implement the warning with the target router or form mechanism. Use `beforeunload` for leaving or reloading the page, and handle in-app navigation through the router or form.

### 7.3. Page editor variant

The source code uses this variant specifically for the Page editor.

- The grid uses `lg:grid-cols-[minmax(0,1fr)_19rem]` for a 304px secondary column. The gap is 20px and increases to 24px from `lg`.
- The form sidebar uses `lg:sticky lg:top-20`, a maximum height of `calc(100dvh - 6rem)`, and internal scrolling.
- The Publish card groups the status with Save & return and Save & continue editing. Both buttons span the column.
- Below `lg`, a fixed bottom save bar has a card background, a top border, and z-index 20. From `md`, the bar starts after the sidebar.
- The save bar accounts for the bottom safe area with `pb-[max(0.75rem,env(safe-area-inset-bottom))]`.
- The form uses `pb-20` on small screens to keep content clear of the save bar.
- A lightly tinted red panel shows an error summary in addition to inline field errors.

**Next.js recommendation.** Use this variant for long forms where save actions need to remain accessible. Choose where to use the bottom bar on a page-by-page basis.

### 7.4. Settings

Settings overview

- Search filters the existing list by setting name and description.
- From `lg`, each group uses two columns, with a 240px (`15rem`) description on the left and content on the right.
- Groups are 32px apart. Item cards use two columns from `sm`.
- Each item uses `p-5`, an icon inside a 40px container with a border and lightly muted background, a 14px semibold title, and a 12px muted description.
- The entire item is a link. An arrow icon appears on hover or focus.

Settings form

- The grid uses `lg:grid-cols-[16rem_minmax(0,1fr)]` with a 20px gap. The 256px description and navigation column sticks at 80px from the top.
- Content is grouped into cards. Sections have dividers and 20–32px spacing.
- The card footer has a Save settings button, a loading state, and an unsaved changes label. Secondary actions sit at the end of the row.
- Secondary navigation wraps in a row on small screens and stacks vertically on desktop. Active items use the secondary color.

### 7.5. Dashboard

- The metric grid uses `gap-4`, with one column → two from `sm` → four from `lg`.
- Metric cards use a 24px icon on a light tinted background, a muted 14px label, and a 24px semibold value with tabular-nums.
- The widget grid uses `gap-5 xl:grid-cols-2`. Some widgets span the full row.
- Widgets have a title and tools in the header. The body shows loading, error with retry, empty, or populated states.
- Loading widgets reserve space with `min-h-48` (192px).
- Users can manage widget visibility and order. Reordering supports controls as well as drag and drop.
- The analytics charts reviewed use the primary color, subtle grid lines, low-opacity fills, and distinct solid and dashed lines. They include tabular data. Use the visual style as a reference with any suitable chart renderer.

### 7.6. Authentication

- Authentication uses a standalone page with a card background and a minimum height equal to the dynamic viewport. The admin sidebar and header are omitted.
- The form is centered in its column, with a maximum width of 384px, 32px between groups, and 20px between fields.
- The form column has a 4px primary-colored top border, a 24px title, and a 14px description.
- With a background image, the layout uses two columns from `lg`. From `xl`, the image column widens using `minmax(24rem,1fr) minmax(0,2fr)`.
- The image appears from `lg`, covers its column, and is centered. It has a dark gradient overlay and the brand name at the bottom.
- The submit button spans the form and has a loading state. The form includes field labels, a remember-me checkbox, and a forgot-password link.

## 8. Feedback, search, and states

### 8.1. Toast

- Toasts appear 16px from the bottom and right edges, with a width of `calc(100% - 2rem)` and a maximum width of 384px.
- Up to four toasts are shown. Toasts with the same content and severity are grouped.
- Each toast uses `rounded-md border border-l-4 bg-card p-4 shadow-lg`, a 20px icon, and 14px title and body text.
- The left border uses primary for success, destructive for errors, amber-500 for warnings, and sky-500 for information. Emerald is reserved for success badges in these patterns.
- Toasts close automatically after about 5 seconds. Hover or focus pauses the timer. Leaving restarts the full 5-second timer.
- Toasts have a close button. Errors and warnings use `role="alert"`. Success and information use `role="status"`.

### 8.2. Global search

- Open from the header or with Ctrl/Cmd + K or Ctrl/Cmd + `/`. Pressing `/` alone also opens search when the event target is the body.
- Opening the dialog focuses the input. Searches are debounced by 200ms, and the previous request is canceled when the query changes.
- Use the arrow keys to select a result, Enter to navigate, and Escape to close the dialog.
- The dialog supports loading, results, no results, and error with retry states. The results list scrolls independently.
- Group names and metadata give links context and distinguish results with the same name.

### 8.3. Interaction states to preserve

| Situation | Feedback pattern |
| --- | --- |
| Submitting a form | Show a spinner in the active submit button, keep its label, and prevent duplicate submissions |
| Updating a table | Show a toolbar spinner, set `aria-busy`, and disable related controls |
| Loading a widget | Reserve the content area and show a spinner with an accessible label |
| No data | Show a message in the relevant content area as an empty state |
| Invalid field | Show an error border and message directly below the field. The form can also include an error summary. |
| Request error | Show a clear error message and offer a retry in the relevant area where possible |
| Success | Show a toast describing the outcome and update the unsaved changes state after saving |
| Pending deletion | Identify the item or scope in the dialog. Use outline for Cancel and destructive for Delete. |
| Unsaved changes | Show an unsaved changes label and warn before leaving the form |

**Next.js recommendation.** Give “no records yet” and “no matches for the current filters” distinct messages. Offer actions to create a record or clear filters where appropriate. The current shared list empty state has only an icon and a message.

### 8.4. Media and long-form content

- The media picker has a preview with a lightly muted background, a border and 6px radius, controls to select or upload an image, and a remove action.
- Aspect ratios vary by context and include 4:3, square, and 5:1 for banners. Featured images use 16:9 and a maximum height of 144px.
- Previews use `object-contain` to show the whole image. When an upload URL is available, the preview accepts dropped files and keeps the action buttons available.
- The content editor has an input area at least 352px (`22rem`) high with 16px padding. Long tables scroll horizontally in their own region.
- Rich text uses a separate heading scale, with h1 at 32px, h2 at 24px, and h3 at 20px. Keep card titles and the admin shell on the UI typography scale.

## 9. Accessibility and motion

**Current implementation in the reviewed code**

- A skip link targets the main area, which has an ID and can receive focus.
- Landmarks and labels identify admin navigation, breadcrumbs, pagination, dialogs, and fieldsets where appropriate.
- Links, buttons, controls, and menus have a focus-visible ring. Icon-only buttons use aria-label in the main patterns.
- Navigation items use `aria-current`, table headers use `scope`, and sorting uses `aria-sort`.
- Labels are linked to fields. Errors and helper text are linked through ARIA, and loading states are announced.
- Radix dialogs and drawers manage focus on open and close. The generic confirmation dialog initially focuses Cancel.
- Motion mainly consists of color and focus transitions and rotating chevrons or spinners. The admin has no shared motion duration.
- With `prefers-reduced-motion: reduce`, animations and transitions are shortened to 0.01ms, animations run once, and scrolling uses auto behavior. Spinners also use `motion-reduce:animate-none`.
- RTL support uses `dir`, logical spacing, and sidebar and chevron adjustments. Test coverage in the target product if it uses RTL.

**Next.js recommendation.** Test keyboard interaction, focus, contrast, and screen reader behavior in the target project. Verify accessibility across the product, including where you reuse Radix, shadcn/ui, or existing tokens.

Use 44px as the reference size for mobile controls. Some targets in the current implementation, such as the toast close and image remove buttons, are smaller than 44px. When building new components, you can expand the touch target while keeping the icon small.

## 10. Applying the design in Next.js + Tailwind + shadcn/ui

This section offers **implementation recommendations for the target project**. You can adopt the design within your existing framework and business logic.

### 10.1. Base CSS for Tailwind v4

The source repository uses Tailwind v4. The snippet below reproduces the default admin colors and utility mappings for target projects using v4. For v3, keep the color variables and map them to the equivalent entries in the existing v3 theme configuration. The `@theme` syntax is specific to v4.

Merge this into the existing global CSS. Keep the imports, plugins, and tokens the product needs. Check existing shadcn/ui color, font, and radius mappings and resolve conflicts when merging.

```css
@import "tailwindcss"; /* Keep a single Tailwind import in the stylesheet. */
@custom-variant dark (&:where(.dark, .dark *));

:root {
  color-scheme: light;
  --background: #f6f7f9;
  --foreground: #20252b;
  --card: #ffffff;
  --sidebar: #eef1f5;
  --primary: #214f7d;
  --primary-ink: #214f7d;
  --primary-foreground: #ffffff;
  --secondary: #e9edf2;
  --secondary-foreground: #354252;
  --accent: #e4eaf1;
  --muted: #eef1f4;
  --muted-foreground: #5d6672;
  --border: #d9dfe6;
  --input: #7c8998;
  --ring: #214f7d;
  --destructive: #b91c1c;
}

.dark {
  color-scheme: dark;
  --background: #171a1f;
  --foreground: #e6ebf1;
  --card: #20252c;
  --sidebar: #1b2027;
  --primary: #95bce5;
  --primary-ink: #95bce5;
  --primary-foreground: #171a1f;
  --secondary: #2d3540;
  --secondary-foreground: #d2dae4;
  --accent: #2a3441;
  --muted: #282f38;
  --muted-foreground: #a5afbd;
  --border: #35404e;
  --input: #728297;
  --ring: #95bce5;
  --destructive: #fc9999;
}

@theme inline {
  --font-sans: var(--admin-font-family, ui-sans-serif, system-ui,
    -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
  --color-background: var(--background);
  --color-foreground: var(--foreground);
  --color-card: var(--card);
  --color-card-foreground: var(--foreground);
  --color-popover: var(--card);
  --color-popover-foreground: var(--foreground);
  --color-sidebar: var(--sidebar);
  --color-sidebar-foreground: var(--foreground);
  --color-primary: var(--primary);
  --color-primary-ink: var(--primary-ink);
  --color-primary-foreground: var(--primary-foreground);
  --color-secondary: var(--secondary);
  --color-secondary-foreground: var(--secondary-foreground);
  --color-accent: var(--accent);
  --color-accent-foreground: var(--foreground);
  --color-muted: var(--muted);
  --color-muted-foreground: var(--muted-foreground);
  --color-border: var(--border);
  --color-input: var(--input);
  --color-ring: var(--ring);
  --color-destructive: var(--destructive);

  /* Set the target project's radii to match the source. */
  --radius-sm: 0.25rem;
  --radius-md: 0.375rem;
  --radius-lg: 0.5rem;
}

@layer base {
  * { @apply border-border; }
  body { @apply bg-background font-sans text-foreground antialiased; }
  ::selection { @apply bg-primary/15 text-foreground; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}
```

These semantic tokens cover the components reviewed. Depending on the target project's components, you may need additional tokens for charts or the shadcn sidebar. Map them by role and check both themes. Follow sections 6 and 9 for link focus, cursors, and component states. Implement theme persistence alongside this CSS.

If the existing shadcn mapping uses `hsl(var(--primary))`, switch to `var(--primary)` for HEX values or convert all colors to a consistent format. Add `primary-ink` as a custom token.

### 10.2. Reference class combinations

```text
Main:
  mx-auto max-w-[1440px] p-4 sm:px-6 sm:py-5 lg:px-8

Page header:
  mb-5 flex flex-wrap items-start justify-between gap-4

Page title:
  text-2xl font-semibold tracking-tight

Card:
  min-w-0 rounded-lg border bg-card text-card-foreground

Form content stack:
  space-y-5

Form grid, shared variant:
  grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_300px]

List toolbar:
  flex flex-wrap items-center gap-3 border-b p-4

Input:
  h-11 w-full min-w-0 rounded-md border border-input bg-card
  px-3 py-2 text-base text-foreground sm:h-9 sm:text-sm
  placeholder:text-muted-foreground/80 outline-none
  transition-[border-color,box-shadow]
  focus-visible:border-ring focus-visible:ring-[3px]
  focus-visible:ring-ring/20
  disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-60
  [&[readonly]]:bg-muted/50
  aria-invalid:border-destructive
  aria-invalid:focus-visible:ring-destructive/20
```

Use these class combinations with shadcn/Radix primitives and the new application's labels, ARIA attributes, state, and data handling.

### 10.3. Responsibilities

| Inherited from the design | Provided by the Next.js project |
| --- | --- |
| Tokens, spacing, typography, and radii | Routes, backend, and business data |
| Shell, sidebar, header, and active styles | Deriving the active state from the target project's route |
| List, filter, and pagination patterns | URL query parameters, data fetching, and pagination logic |
| Fields, errors, loading, and unsaved changes indicator | Validation, submission, and form management |
| Confirmation dialogs and toasts | Mutations and notification content |
| Light, Dark, and System themes | SSR-compatible theme persistence and initialization |
| Menu groups and permission-based states | Permission model and backend access checks |

A suggested structure includes `AdminShell`, `PageHeader`, `FormSection`, `DataTableToolbar`, `TablePagination`, and shared UI primitives. The menu takes the product's configuration. The Next.js application layer connects the active state, data, router, and session. Set client boundaries around interactive components so the rest of the app can use its existing rendering model.

Kern's localStorage keys, endpoints, `@admin` alias, Inertia props, page registry, and session layer are specific to the source application. Define the corresponding names and mechanisms in the target project.

### 10.4. Implementation order and verification

1. Set up the light and dark tokens and check the target project's fonts, radii, and breakpoints.
2. Customize Button, Input, Select, Card, Badge, Dialog, DropdownMenu, and Table using the specifications above.
3. Build the shell with a 256/72px sidebar, 64px header, main area, and page header.
4. Build one list page with filtering, sorting, pagination, empty and loading states, and delete confirmation.
5. Build one form with errors, unsaved changes, loading, and save actions. Choose between the 300px secondary column and the Page variant based on the form's needs.
6. Review the examples in light and dark modes and at the viewport widths in section 5. Agree on the patterns before rolling them out.

Suggested acceptance checklist

- [ ] The page background, surfaces, input borders, and card borders are visually distinct in both themes.
- [ ] The primary color, text on primary, and primary-ink serve their intended roles. Destructive buttons remain legible.
- [ ] H1 is 24px and card titles are 14px. Fields and tables retain their UI sizes independently of rich text headings.
- [ ] The shell matches the specified dimensions. Collapsing the sidebar updates the main area and the save bar where present.
- [ ] The mobile drawer opens, closes, and restores focus. The header fits at 375px.
- [ ] The toolbar wraps, wide tables scroll within their containers, and long descriptions or errors fit within the grid.
- [ ] Fields have labels, required states, and helper or error text as appropriate. Tab and Enter work as intended.
- [ ] Loading prevents duplicate submissions and preserves context. Data survives validation failures.
- [ ] Sorting, filtering, and page size have clear states and corresponding URL parameters. The scope of Select all is clear.
- [ ] Dialogs fit within the viewport, focus works as intended, and menus and selects inside dialogs remain visible.
- [ ] The mobile save bar respects the safe area and leaves the last field visible.
- [ ] Success and error feedback, unsaved changes warnings, and delete confirmation are present.
- [ ] Reduced motion, System theme, and page reloads work as intended.

Centralize z-index management. The source uses 20 for the header and bottom bar, 30 for the sidebar, 40/50 for the mobile overlay and drawer, and 50/51 for the first dialog's overlay and content, increasing for nested dialogs. It uses 70 for dropdowns, 130 for selects, 200 for toasts, and 80 for the skip link. Treat these current values as a starting point and test actual overlay combinations before defining the new app's layer scale.

### 10.5. AI implementation prompt for the target project

> Read design-system.md and use it as a reference to reproduce the Kern Admin style in the current project. Keep Next.js, Tailwind CSS, shadcn/ui, and the existing business architecture. Prioritize the documented tokens, shell, typography, control sizes, cards, tables, and interaction states. Apply the current implementation and recommendations as identified in the document. Preserve the style and effects it describes. Check the Tailwind version and the existing color and radius mappings before applying the design. Start with a list page and a form covering loading, validation, empty states, and delete confirmation, then review responsive behavior and light and dark modes before expanding. Use the document on its own when the specifications are sufficient. Ask for clarification when business details are missing.

## 11. Source files and traceability

These paths are in the **original Kern CMS repository** and are provided for reference if you have access to it. The Next.js project can use this document independently. In the table, `base` refers to `platform/core/base/resources/js/admin`.

| Area | Source files |
| --- | --- |
| Stack and preset | `package.json`, `components.json` |
| Colors, fonts, containers, reduced motion, and rich text | `platform/core/base/resources/css/admin.css` |
| Shell, header, page heading, compact navigation | `base/layouts/admin-layout.tsx` |
| Theme and appearance overrides | `base/lib/theme.ts`, `base/lib/appearance.ts`, `base/components/admin-appearance.tsx`, `base/components/theme-switch.tsx` |
| Navigation, drawer, and scroll regions | `base/components/ui/sidebar.tsx` |
| UI primitives | `base/components/ui/{button,input,card,badge,table,select,checkbox,radio,dialog,dropdown-menu}.tsx` |
| Fields and form groups | `base/components/field.tsx`, `base/components/form-section.tsx` |
| Data lists | `base/components/content-resource-index.tsx`, `base/components/data-table-toolbar.tsx`, `base/components/filter-toolbar.tsx`, `base/components/search-input.tsx`, `base/components/table-pagination.tsx` |
| Query and page size | `base/lib/table-query.ts`, `base/lib/pagination.ts` |
| Shared form and unsaved changes guard | `base/components/content-resource-form.tsx`, `base/lib/use-unsaved-changes.ts` |
| Dedicated Page form | `platform/packages/page/resources/js/admin/Form.tsx` |
| Toast, confirm, loading, global search | `base/components/{toast,confirm-dialog,loading-indicator,global-search}.tsx` |
| Media and uploads | `base/components/media-picker.tsx`, `base/components/file-upload.tsx` |
| Settings | `platform/core/setting/resources/js/admin/Settings/{Index,Form,settings-section,section-nav}.tsx` |
| Dashboard | `platform/core/dashboard/resources/js/admin/Dashboard/{Index,analytics-visuals}.tsx`, `platform/core/dashboard/resources/js/admin/components/{widget-panel,widget-loading}.tsx` |
| Authentication | `platform/core/acl/resources/js/admin/Auth/Form.tsx` |

When the original admin interface changes, update the specifications and the reviewed commit before using this document as the reference for the new version. Verify the Next.js recommendations in the target app, especially SSR and theme handling, customized components, and accessibility.
