---
name: SAE — Sistema de Administración Escolar
description: All-in-one school management platform for Spanish-speaking educational communities
colors:
  primary: "#6366F1"
  primary-deep: "#4F46E5"
  primary-light: "#818CF8"
  info: "#4285F4"
  success: "#22C55E"
  warning: "#F59E0B"
  error: "#EF4444"
  neutral: "#64748B"
  "neutral-bg": "#F1F5F9"
  "neutral-bg-elevated": "#FFFFFF"
  "surface-container": "#E2E8F0"
  "surface-container-low": "#F8FAFC"
  "surface-container-lowest": "#FFFFFF"
  text-on-primary: "#FFFFFF"
  text-on-surface: "#0F172A"
  text-muted: "#64748B"
typography:
  display:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: "clamp(1.5rem, 4vw, 2.5rem)"
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: "-0.02em"
  body:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.6
    letterSpacing: "normal"
  label:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: 1.4
    letterSpacing: "0.05em"
    textTransform: "uppercase"
rounded:
  sm: "4px"
  md: "8px"
  lg: "12px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.text-on-primary}"
    rounded: "{rounded.sm}"
    padding: "10px 24px"
    fontWeight: 600
    fontSize: "0.875rem"
    boxShadow: "0 2px 8px rgba(99, 102, 241, 0.3)"
    transition: "all 0.2s ease"
  button-primary-hover:
    backgroundColor: "{colors.primary-deep}"
    boxShadow: "0 4px 16px rgba(99, 102, 241, 0.4)"
    transform: "translateY(-1px)"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.text-on-surface}"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
    border: "1px solid {colors.surface-container}"
    transition: "all 0.2s ease"
  button-info:
    backgroundColor: "{colors.info}"
    textColor: "{colors.text-on-primary}"
    rounded: "{rounded.sm}"
    padding: "10px 24px"
    fontWeight: 600
  button-warning:
    backgroundColor: "{colors.warning}"
    textColor: "{colors.text-on-surface}"
    rounded: "{rounded.sm}"
    padding: "10px 24px"
    fontWeight: 600
  button-success:
    backgroundColor: "{colors.success}"
    textColor: "{colors.text-on-primary}"
    rounded: "{rounded.sm}"
    padding: "10px 24px"
    fontWeight: 600
  card:
    backgroundColor: "{colors.neutral-bg-elevated}"
    rounded: "{rounded.lg}"
    borderTop: "4px solid {colors.primary}"
    boxShadow: "0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)"
    padding: "{spacing.lg}"
    border: "1px solid {colors.surface-container}"
  card-hover:
    boxShadow: "0 4px 12px rgba(0,0,0,0.1)"
    transform: "translateY(-1px)"
  input:
    backgroundColor: "{colors.neutral-bg-elevated}"
    border: "1px solid {colors.surface-container}"
    rounded: "{rounded.sm}"
    padding: "10px 14px"
    fontSize: "0.875rem"
    focusBorder: "2px solid {colors.primary}"
    focusRing: "0 0 0 3px rgba(99, 102, 241, 0.15)"
  alert:
    borderLeft: "4px solid {colors.primary}"
    rounded: "{rounded.sm}"
    padding: "{spacing.md}"
    backgroundColor: "{colors.neutral-bg}"
  chip:
    rounded: "{colors.full}"
    padding: "4px 12px"
    fontSize: "0.75rem"
    fontWeight: 600
---

# Design System: SAE — Sistema de Administración Escolar

## Overview

**Creative North Star: "The Academic Dashboard"**

SAE's design language blends the warmth of a well-organized classroom with the precision of modern software. The palette leans into a confident blue-indigo spectrum that evokes trust, clarity, and intellectual rigor — never cold, always approachable. Surfaces carry gentle depth through layered shadows and subtle backdrop blurs, creating a sense of physicality that makes every interaction feel deliberate and grounded.

The system prioritizes scanability and efficient task completion. Teachers and students move through dense information landscapes with clear visual hierarchies, generous spacing, and consistent component behavior. Cards feel like index cards on a corkboard — tangible, organized, and within arm's reach. Buttons are lifted and responsive, catching the eye when action is available and receding when it is not.

**Key Characteristics:**
- Blue-indigo primary palette with warm undertones, not sterile cool
- Elevated, tactile components with subtle shadow depth
- Sidebar-first navigation with collapsible drawers and Alpine.js reactivity
- Glass-morphism accents on login and overlay surfaces
- Consistent 4px-8px rounding on inputs and buttons, 12px on cards
- Gradient text on brand marks (indigo-500 to purple-600)

## Colors

The SAE palette is anchored in a blue-indigo primary (`#6366F1`) that carries the energy of trust and intellectual depth. The `--color-info: #4285F4` CSS custom property provides a brighter blue accent for interactive states and informational elements. DaisyUI's semantic color system (`primary`, `success`, `warning`, `error`, `info`) provides the functional color layer, while `base-200` and `base-100` define the surface hierarchy.

### Primary
- **Indigo Blue** (`#6366F1`): The primary brand color. Used for main CTAs, active tabs, card border accents, and navigation highlights. The brand mark uses a gradient from indigo-500 to purple-600 via `bg-gradient-to-r from-indigo-500 to-purple-600 bg-clip-text text-transparent`.
- **Indigo Deep** (`#4F46E5`): Hover state for primary buttons and deeper accent elements.
- **Indigo Light** (`#818CF8`): Subtle backgrounds and focus rings (`rgba(99, 102, 241, 0.15)`).

### Secondary
- **Info Blue** (`#4285F4`): Defined in `:root { --color-info: #4285F4 }`. Used for informational accents, chat message bubbles, links, and avatar rings.

### Tertiary
- **Success Green** (`#22C55E`): Positive states, message bubbles from the sender (`bg-success/20`), checkmark icons.
- **Warning Amber** (`#F59E0B`): Pending items, warnings (`bg-warning/5`, `text-warning`), dashboard stat indicators.
- **Error Red** (`#EF4444`): Destructive actions, errors (`bg-error/10`, `text-error`), unread badges (`badge-error`).

### Neutral
- **Surface Container** (`#E2E8F0`): Default borders and dividers (`border-base-300`).
- **Surface Container Low** (`#F8FAFC`): Subtle card backgrounds and hover states (`bg-base-100`).
- **Surface Container Lowest** (`#FFFFFF`): Card and form backgrounds.
- **Text on Surface** (`#0F172A`): Primary text color.
- **Text Muted** (`#64748B`): Secondary text, timestamps, labels (`text-base-content/60`).
- **Text on Primary** (`#FFFFFF`): Inverted text on colored buttons and active tabs.

### Named Rules
**The Indigo Anchor Rule.** The primary indigo (`#6366F1`) anchors every meaningful action and state transition. It appears on no more than 15% of any given screen's surface area. Its rarity is the point — when it appears, the eye knows something matters.

**The Warm Efficiency Rule.** Every color choice serves both warmth and efficiency. No decorative-only colors exist; each hue carries a functional role (action, status, warning, error, or neutral hierarchy).

## Typography

**Display Font:** Inter, system-ui, sans-serif
**Body Font:** Inter, system-ui, sans-serif
**Label Font:** Inter, system-ui, sans-serif (uppercase, tracked)

**Character:** Clean, modern, and unpretentious. Inter provides excellent legibility at small sizes for dense data tables and long reading, while the system-ui fallback ensures instant rendering across platforms. The type system prioritizes information density — teachers scanning grades, students checking deadlines — without sacrificing warmth through generous line heights and measured font sizes.

### Hierarchy
- **Display** (700, `clamp(1.5rem, 4vw, 2.5rem)`, line-height 1.1): Used for page titles and the login header (`text-2xl font-bold`). Slightly condensed letter-spacing (`-0.02em`).
- **Headline** (700, `1.5rem`, line-height 1.2): Section titles within cards and the login logo (`text-xl font-bold`).
- **Title** (600, `1.125rem`, line-height 1.4): Card titles and prominent labels (`font-semibold`).
- **Body** (400, `0.875rem`, line-height 1.6): Default body text, input labels, descriptions. Max line length ~65ch.
- **Label** (600, `0.75rem`, letter-spacing 0.05em, uppercase): Form labels, badges, tab labels, navigation items (`uppercase`, `font-semibold`, `text-xs`).

### Named Rules
**The Dense Data Rule.** Body text never drops below `0.875rem` — teachers scanning grades and attendance records need legibility in dense layouts. Labels are always uppercase with `0.05em` letter-spacing for immediate scannability.

**The Gradient Brand Rule.** The brand name uses `bg-gradient-to-r from-indigo-500 to-purple-600 bg-clip-text text-transparent`. This is the only gradient text in the system. Never apply gradients to body copy, buttons, or labels.

## Layout

SAE uses a sidebar-first layout model. The main application shell (`<x-main>`) provides a collapsible sidebar drawer (`drawer="main-drawer"`) hidden on mobile (`lg:hidden`) and visible on desktop. Navigation is driven by Alpine.js (`x-data`, `x-show`, Alpine store patterns) with Livewire server-side state.

**Grid System:** Tailwind CSS 4 responsive grid. Dashboard uses `grid grid-cols-1 gap-4 md:grid-cols-2` for card layouts. Forms and lists stack vertically on mobile (`grid-cols-1`) and expand to 2-4 columns on medium+ breakpoints (`md:grid-cols-2 md:grid-cols-4`).

**Container Pattern:** `mx-auto` centers content. Login uses `w-3/4 md:w-1/3 mx-auto` for constrained forms. Sidebar has `p-4 pt-3` padding with `rounded bg-black/20` for the user section.

**Responsive Behavior:**
- Mobile (`< lg`): Sidebar collapses to a nav icon bar (`<x-nav sticky class="lg:hidden">`). Single-column layouts. Hamburger menu (`o-bars-3`) opens drawer.
- Desktop (`≥ lg`): Full sidebar visible. Multi-column grids. Two-column chat layout (`lg:flex-row`).

**Spacing Rhythm:** Consistent 4px grid. Base spacing unit is `8px` (`p-2`, `gap-2`). Card padding is `16px` (`p-4`). Section gaps are `24px` (`gap-4`, `my-4`).

**Background:** `bg-base-200/50` on light mode, `dark:bg-base-200` on dark mode. The clean layout supports `bg-cover bg-center` background images (`background.jpg`). Glass-morphism overlays use `bg-slate-800/30 backdrop-blur-sm` and `bg-slate-900/70 backdrop-blur-xl`.

## Elevation & Depth

SAE uses a **lifted** elevation philosophy. Components have physical presence through layered box-shadows and hover translations, conveying that each interactive element is tangible and within reach. Cards feel like index cards lifted off a corkboard.

**Shadow Vocabulary:**
- **Resting** (`box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06)`): Default card shadow (`shadow-md`). Cards sit on the surface.
- **Hover** (`box-shadow: 0 4px 12px rgba(0,0,0,0.1)`): Card hover state (`card-hover`). Card lifts off the surface.
- **Prominent** (`box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3)`): Primary button shadow. The button feels pressable and clickable.
- **Strong** (`box-shadow: 0 4px 24px rgba(0,0,0,0.12)`): Login card shadow (`shadow-lg shadow-black/50`). The login modal is elevated above everything.
- **Focus Ring** (`box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15)`): Input focus (`focusRing`). Subtle ring indicating focus.

### Ring Effects
- **Avatar Ring** (`ring-2 ring-info/30 ring-offset-2 ring-offset-base-200`): Chat message avatars use an indigo-tinted ring for visual separation.

### Named Rules
**The Corkboard Rule.** Cards have `border-t-4 border-t-primary` as a top accent stripe, mimicking the physical tab on a file folder. This `border-t-4` treatment is the SAE signature — it appears on every `<x-card>`. Cards feel like tangible objects with a visible "label tab."

**The Lifted Button Rule.** Primary buttons have an elevated shadow (`0 2px 8px rgba(99, 102, 241, 0.3)`) and a subtle upward translate on hover (`translateY(-1px)`). They look and feel pressable — the user's finger wants to push them down.

**The Flat Background Rule.** Background surfaces are intentionally flat and low-contrast (`bg-base-200/50`). Depth comes only from components, never from the background itself. This keeps the focus on content and interaction.

## Shapes

SAE uses **generous rounding** that balances friendliness with professionalism. Small elements (buttons, inputs, badges) use `4px` (`rounded-sm`) rounding, while containers (cards, message bubbles) use `8-12px` (`rounded-lg`, `rounded`). The chat bubble is an outlier at `22px` (`rounded-[22px]`) with a distinctive `rounded-br-[4px]` bottom-right corner for the sender's bubble tail.

**Corner Strategy:**
- **Buttons/Inputs:** `4px` (`rounded-sm`) — clean, clickable targets
- **Cards/Containers:** `12px` (`rounded-lg`) — friendly containers
- **Avatars:** `9999px` (`rounded-full`) — circular, friendly identity marks
- **Chat Bubbles:** `22px` (`rounded-[22px]`) with `4px` bottom-right — distinctive conversation elements
- **Card Accent:** `border-t-4` — the top accent stripe with square corners on the rest

**Border Treatment:** `border border-base-300` is the standard border for cards, sidebar sections, and chat containers. It provides subtle definition without heaviness.

### Named Rules
**The Accent Stripe Rule.** Every card gets `border-t-4 border-t-[color]` as a top border accent. The color matches the card's category (primary for the main panel, warning for payments, info for messages). Never use `border-t` without the `4px` width.

**The Soft Corner Rule.** All user-facing containers use `rounded-lg` or larger. Sharp corners (`rounded-0` or no radius) are reserved for decorative elements only. The system is friendly — sharp corners are unfriendly.

## Components

### Buttons
- **Shape:** `4px` radius (`rounded-sm`), with `btn-circle` using `rounded-full`
- **Primary:** `bg-primary` (`#6366F1`), white text, `10px 24px` padding, `font-semibold`, `shadow: 0 2px 8px rgba(99, 102, 241, 0.3)`, `transition: all 0.2s ease`, hover lifts with `translateY(-1px)` and enhanced shadow
- **Ghost:** Transparent background, `border: 1px solid {colors.surface-container}`, `8px 16px` padding, hover fills with `base-200`
- **Success/Warning/Info:** Semantic colors (`bg-success`, `bg-warning`, `bg-info`), white text, `10px 24px` padding
- **Sizes:** `btn-xs` (`text-xs`, compact), `btn-sm` (`text-sm`, compact), default (`text-sm`)
- **Hover:** Primary darkens to `primary-deep`, ghost highlights background, all lift slightly
- **Circle:** `btn-circle` uses `rounded-full` for icon-only buttons (profile, power, settings)

### Cards
- **Shape:** `12px` radius (`rounded-lg`), `border: 1px solid {colors.surface-container}`, `border-top: 4px solid {colors.primary}` accent stripe
- **Background:** `bg-white` or `bg-primary/5` for category cards
- **Shadow:** Resting `shadow-md`, hover `shadow-lg` with `translateY(-1px)`
- **Padding:** `24px` (`p-4`) internal padding
- **Title:** Left-aligned, `font-bold`, `text-xl` or `text-lg`, with `mb-4` spacing to content
- **Variant Cards:** `bg-warning/5 border-t-4 border-t-warning` for payment cards, `bg-success/5 border-t-4 border-t-success` for positive indicators

### Inputs / Fields
- **Style:** `1px solid {colors.surface-container}` border, `4px` radius (`rounded-sm`), `10px 14px` padding, white background
- **Focus:** `2px solid {colors.primary}` border with `0 0 0 3px rgba(99, 102, 241, 0.15)` ring
- **With Icon:** Left icon slot (`icon="o-envelope"`, `icon="o-key"`), optional append slot for buttons
- **Label:** `text-sm font-semibold` label above the input, `mb-2` spacing
- **Inline:** `inline` modifier for compact horizontal layout (login form)
- **Textarea:** `bg-base-100/90`, focus `bg-base-100`, `resize` disabled

### Navigation
- **Sidebar:** `bg-base-100` sidebar with `bg-black/20` user section (`-mx-2 !-mt-2 rounded`), `p-4 pt-3` brand area, scrollable menu items
- **Menu Items:** `x-menu-item` with icon (`o-sparkles`, `o-calendar`, `o-users`), `text-sm`, hover backgrounds
- **Menu Sub:** Collapsible submenus with `x-menu-sub`, Alpine.js `x-data`, `setTimeout` auto-close of sibling submenus
- **Active Route:** `activate-by-route` on `<x-menu>`, active items highlighted with `text-primary`
- **Mobile Nav:** `<x-nav sticky class="lg:hidden">` with brand and action slots, hamburger drawer toggle

### Chips / Badges
- **Badge:** `badge badge-sm` with semantic colors (`badge-error text-white`, `badge-info`), used for unread counts
- **Chip:** `rounded-full` pill shape, `4px 12px` padding, `font-semibold`, `text-xs`

### Alerts / Toasts
- **Alert:** `border-left: 4px solid {color}`, `bg-base-200` background, `rounded-sm`, `p-4` padding
- **Alert Variants:** `alert-warning`, `alert-info`, `alert-success`, `alert-sm` (compact)
- **Toast:** `<x-toast>` component positioned fixed at bottom, auto-dismissing notifications

### Tabs
- **Style:** `tabs tabs-boxed bg-base-200 grid grid-cols-2 rounded-t-lg`
- **Active Tab:** `tab-active !bg-primary !text-primary-content rounded-t-lg` — solid primary background with white text
- **Transition:** `transition-all duration-200` on tab switch

### Chat Bubbles
- **Sender Bubble:** `bg-success/20 border border-success/30 rounded-[22px] rounded-br-[4px] shadow-sm`, text-white
- **Receiver Bubble:** `bg-info/10 border border-info/20 rounded-[22px] rounded-bl-[4px] shadow-sm`, text-base-content
- **Avatar:** `rounded-full ring-2 ring-info/30 ring-offset-2` with `w-8 h-8` size
- **Timestamp:** `text-[10px] opacity-60 text-right`, positioned below message

### Tables
- **Row Decoration:** Conditional styling via `:row-decoration` prop, `text-red-500` for disabled users
- **Avatar in Cell:** `x-avatar` with `!w-8 !h-8`, circular
- **Actions:** `<x-dropdown>` with `btn-ghost btn-sm` trigger, hover-activated dropdown menu
- **Striped:** `:striped` prop for alternating row colors

### Named Rules
**The Top Stripe Rule.** Every `<x-card>` gets `border-t-4 border-t-[category-color]`. This is the visual signature of SAE — never a card without its accent stripe. Primary panels use `border-t-primary`, warnings use `border-t-warning`, info uses `border-t-info`.

**The Button Hover Lift Rule.** All buttons have `transition: all 0.2s ease`. Primary buttons lift (`translateY(-1px)`) and gain shadow on hover. Ghost buttons fill background on hover. Every button interaction has physical feedback.

**The Tab Solidity Rule.** Active tabs use `!bg-primary !text-primary-content` — full primary background with white text. No outline or ghost treatment for active tabs. This is non-negotiable for SAE's navigation clarity.

## Do's and Don'ts

### Do:
- **Do** use the indigo primary (`#6366F1`) for all primary actions and card accent stripes — it is the system's visual anchor and appears on no more than 15% of any screen
- **Do** maintain the `border-t-4` card accent stripe on every `<x-card>` — it is the SAE signature and provides instant category recognition
- **Do** use `rounded-[22px]` for chat bubbles with the distinctive `rounded-br-[4px]` tail — this is a unique system pattern
- **Do** apply `backdrop-blur-xl` and `bg-slate-900/70` for login surfaces — the glass-morphism effect creates depth and focus
- **Do** use `bg-gradient-to-r from-indigo-500 to-purple-600 bg-clip-text text-transparent` exclusively for the brand name text — no other gradient text exists
- **Do** keep body text at `0.875rem` minimum for dense data scanning — teachers need legibility in grade tables
- **Do** use Alpine.js `x-data`, `x-show`, `x-transition` for client-side interactivity alongside Livewire server-side state
- **Do** use `font-sans` consistently — the Inter/system-ui stack provides instant rendering and excellent legibility

### Don't:
- **Don't** use purple (`#570df8`) as a standalone primary — the direction is blue-indigo; purple appears only as the secondary gradient end on the brand mark
- **Don't** add sharp corners (`rounded-0` or no radius) to any user-facing component — the system is friendly and rounded
- **Don't** remove the `border-t-4` accent stripe from cards — this is the non-negotiable SAE visual signature
- **Don't** use decorative-only colors — every color must serve a functional role (action, status, warning, error, or hierarchy)
- **Don't** apply gradient text to anything other than the brand name — gradients are reserved for the logo
- **Don't** stack more than two levels of shadow elevation — rest (shadow-md), hover (shadow-lg), and strong (shadow-lg/black) are the maximum
- **Don't** use `font-serif` or display fonts — the system uses `font-sans` (Inter) exclusively for efficiency and screen legibility
- **Don't** make buttons flat — buttons must have elevated shadows (`0 2px 8px rgba(99, 102, 241, 0.3)`) and hover lift to feel tactile and pressable
