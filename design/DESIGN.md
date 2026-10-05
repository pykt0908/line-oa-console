---
name: Institutional Desk
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#3e4a3d'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#6e7b6c'
  outline-variant: '#bdcaba'
  surface-tint: '#006e2d'
  primary: '#006b2c'
  on-primary: '#ffffff'
  primary-container: '#00873a'
  on-primary-container: '#f7fff2'
  inverse-primary: '#62df7d'
  secondary: '#006d30'
  on-secondary: '#ffffff'
  secondary-container: '#92f5a4'
  on-secondary-container: '#007233'
  tertiary: '#0051d5'
  on-tertiary: '#ffffff'
  tertiary-container: '#316bf3'
  on-tertiary-container: '#fefcff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#7ffc97'
  primary-fixed-dim: '#62df7d'
  on-primary-fixed: '#002109'
  on-primary-fixed-variant: '#005320'
  secondary-fixed: '#95f8a7'
  secondary-fixed-dim: '#79db8d'
  on-secondary-fixed: '#00210a'
  on-secondary-fixed-variant: '#005323'
  tertiary-fixed: '#dbe1ff'
  tertiary-fixed-dim: '#b4c5ff'
  on-tertiary-fixed: '#00174b'
  on-tertiary-fixed-variant: '#003ea8'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  headline-xl:
    fontFamily: Noto Sans
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Noto Sans
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 26px
    letterSpacing: -0.01em
  headline-md:
    fontFamily: Noto Sans
    fontSize: 15px
    fontWeight: '600'
    lineHeight: 22px
    letterSpacing: 0em
  body-lg:
    fontFamily: Noto Sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
    letterSpacing: 0em
  body-md:
    fontFamily: Noto Sans
    fontSize: 13px
    fontWeight: '400'
    lineHeight: 18px
    letterSpacing: 0em
  body-sm:
    fontFamily: Noto Sans
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
    letterSpacing: 0.01em
  label-lg:
    fontFamily: Noto Sans
    fontSize: 13px
    fontWeight: '600'
    lineHeight: 18px
    letterSpacing: 0.01em
  label-md:
    fontFamily: Noto Sans
    fontSize: 11px
    fontWeight: '600'
    lineHeight: 14px
    letterSpacing: 0.02em
  label-sm:
    fontFamily: Noto Sans
    fontSize: 10px
    fontWeight: '700'
    lineHeight: 12px
    letterSpacing: 0.03em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 0px
  margin: 0px
  space-xs: 4px
  space-sm: 8px
  space-md: 12px
  space-lg: 16px
  space-xl: 24px
---

## Brand & Style

This design system drives a high-volume, institutional multi-account LINE Official Account (OA) desktop console. The emotional tone is authoritative, composed, and razor-sharp—instilling confidence during rapid customer support and critical communication workflows.

### Aesthetic Paradigm
- **Movement:** Modern Institutional Desk with structured flat architecture, inspired by precision enterprise application systems (Vuetify 3 desktop ergonomics).
- **Core Attributes:** Dense information presentation without clutter, high visual scanning speed, deterministic state feedback, and crisp structural segmentation.
- **Visual Weight:** Light, neutral framing that puts user conversations, operational statuses, and multi-tenant LINE channels front and center.

## Colors

The palette balances the recognizable brand green of LINE Official Account ecosystem with enterprise-grade slate neutrals and rigorous semantic signals.

### Color Tokens & Hierarchy
- **Primary (`#16A34A`):** Applied to active channel badges, main interactive triggers, verified ticks, and resolved states.
- **Primary Dark / Hover (`#15803D`):** Interactive state for primary CTA hover, pressed nodes, and dark channel indicators.
- **Secondary / Info (`#2563EB`):** System dispatches, auto-responder notes, and internal routing tags.
- **Warning (`#D97706`):** Escalated customer SLAs, unassigned live queues, pending rich menu actions.
- **Danger (`#DC2626`):** Blocked contacts, failed webhook deliveries, deleted messages.
- **Neutral Foreground (`#0F172A`):** High-contrast primary copy, headers, and conversation logs.
- **Muted Text (`#64748B`):** Timestamps, secondary labels, metadata, and placeholder text.
- **Surface Canvas (`#F8FAFC`):** Application canvas, inactive panes, and gutter backdrops.
- **Surface Layer (`#FFFFFF`):** High-focus card surfaces, active chat bubbles, thread viewports, and modals.
- **Border Default (`#E2E8F0`):** Single-pixel perimeter divisions across panels, inputs, and tab bars.

## Typography

The typography uses Noto Sans (compatible with unified Latin and Noto Sans Thai metrics) to ensure seamless bilingual parity between English and Thai customer communications without visual baseline disruption.

- **Dual-Script Legibility:** Noto Sans maintains open counters and consistent x-height across Thai tonemark extensions and Latin glyphs, preventing text-clipping inside condensed inbox lists.
- **Information Density:** Body copy defaults to 13px/18px line height to allow operators to review extended chat histories without excessive scrolling.
- **Systematic Labeling:** All structural metadata, badges, and timestamps utilize explicit 10px–11px semi-bold or bold treatments with micro-letter-spacing for instant legibility in high-density queues.

## Layout & Spacing

The layout is built as a strict, full-viewport desktop console utilizing a fixed-column docking model. Margins and gutters between major panes are zeroed (`0px`), relying entirely on 1px structural dividing lines.

### 3-Column Desk Architecture
- **Column 1 — OA Switcher & Channel List (Width: 280px–320px):**
  - Houses tenant selector, global search, and filtered message threads.
  - Sticky header with integrated status chips (`Active`, `Archived`, `Follow-up`).
- **Column 2 — Active Conversation Stage (Width: Flex 1 1 500px):**
  - Unbroken conversation viewport.
  - Pinned message header (User display name, LINE user ID, channel avatar).
  - Bottom-docked multi-format composition bar with quick-reply templates.
- **Column 3 — CRM, User Attributes & Audit Log (Width: 320px–360px):**
  - Accordion panels with LINE metadata, assigned agents, custom tagging, order lookups, and past ticket archives.

### Responsive Behaviors
- **Desktop (>= 1280px):** Full 3-column expanded state with persistent inspector panel.
- **Tablet Landscape / Small Desktop (1024px – 1279px):** 3rd column converts to a slide-over drawer accessible via an inspector icon in the chat header.
- **Compact Viewport (< 1024px):** Single-pane master-detail view toggle between the thread list and the conversation stage.

## Elevation & Depth

To maximize clarity and reduce visual fatigue across 8-hour agent shifts, this system replaces heavy ambient shadows with flat, high-definition planar boundaries.

- **Dividers & Borders:** Surface layers are isolated strictly with a 1px solid border (`#E2E8F0`).
- **Surface Elevation:**
  - **Level 0 (Base Canvas):** `#F8FAFC` for application frame, thread list background, and sidebars.
  - **Level 1 (Card/Thread Focus):** `#FFFFFF` for the active chat bubble, active selected thread item, and modal dialogues.
- **Shadow Profile:** Shadows are reserved only for floating dropdown menus, context popovers, and quick-reply overlays:
  - `0 4px 6px -1px rgba(15, 23, 42, 0.06), 0 2px 4px -2px rgba(15, 23, 42, 0.04)`

## Shapes

The design system employs a **Soft (1)** shape language:
- Standard UI nodes (buttons, text fields, cards, and list rows) feature a precise `4px` (0.25rem) radius.
- Moderate containers and flyout menus utilize `8px` (`rounded-lg`).
- Status indicator dots, user presence marks, and pill-tags use circular/capsule bounds where functional distinction is required.
- Chat message bubbles use asymmetrical curvature: incoming bubbles feature `8px` with top-left `2px`; outgoing bubbles use `8px` with top-right `2px`.

## Components

### Buttons
- **Primary:** Background `#16A34A`, foreground `#FFFFFF`, border-radius 4px, font size 13px, font weight 600. Hover state: `#15803D`.
- **Secondary:** Surface `#FFFFFF`, border `1px solid #E2E8F0`, foreground `#0F172A`. Hover: background `#F8FAFC`.
- **Ghost / Icon:** Transparent background, text `#64748B`, hover background `#F1F5F9`, hover foreground `#0F172A`.

### Status Chips & Badges
- **LINE Account Tag:** Light green background `#DCFCE7`, text `#15803D`, border `1px solid #BBF7D0`, 11px uppercase bold.
- **Escalation Badge:** Amber background `#FEF3C7`, text `#92400E`, border `1px solid #FDE68A`.
- **System Notification Chip:** Blue background `#EFF6FF`, text `#1D4ED8`, border `1px solid #BFDBFE`.
- **Resolved Badge:** Slate background `#F1F5F9`, text `#475569`, border `1px solid #E2E8F0`.

### Inbox List Rows
- **Height & Spacing:** Dense 68px item blocks with vertical auto-layout.
- **Active State:** Solid `#FFFFFF` background with a 3px vertical accent indicator `#16A34A` along the left border.
- **Inactive State:** Background `#F8FAFC`, hover state `#F1F5F9`.
- **Content:** Customer avatar (36px, rounded), LINE display name, snippet text truncated to 1 line (`#64748B`), timestamp, and unread counter badge.

### Input Fields & Search Bars
- **Frame:** `#FFFFFF` background, `1px solid #E2E8F0`, 4px corner radius, padding 6px 12px.
- **Focus:** Border `#16A34A` with zero outer glow; subtle inner focus ring `1px solid #16A34A`.
- **Quick-reply Textarea:** Fixed bottom dock in Column 2, internal vertical padding 10px, clear attachment trigger icons.

### Checkboxes & Selection Nodes
- **Box Dimensions:** 16px × 16px, 3px border radius.
- **Default State:** Border `1px solid #CBD5E1`, background `#FFFFFF`.
- **Selected State:** Border `#16A34A`, background `#16A34A`, check glyph `#FFFFFF`.