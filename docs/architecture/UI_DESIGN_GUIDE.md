# UI Design Guide

目前 Vue 管理後台的視覺與互動規範。這是對既有介面的整理，不是獨立 design-system package。

## Principles

- 資訊層級優先於裝飾
- 管理操作必須顯示 loading、success 與 error state
- destructive action 必須可辨識且要求確認
- desktop table 必須有 mobile fallback
- role-specific navigation 不顯示無權限項目
- 色彩不能是狀態的唯一辨識方式

## Layout

Page shell:

```html
<div class="min-h-screen bg-gray-50 px-4 py-6 sm:px-6 lg:px-8">
  <div class="mx-auto max-w-7xl">
    ...
  </div>
</div>
```

Card:

```html
<section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
  <header class="border-b border-gray-200 bg-gray-50 px-6 py-4">
    ...
  </header>
  <div class="p-6">...</div>
</section>
```

Use `space-y-*` for vertical rhythm and `gap-*` for grid/flex children. Avoid mixing arbitrary margins when a parent spacing utility can express the relationship.

## Typography

| Purpose | Suggested classes |
| --- | --- |
| Page title | `text-2xl font-bold text-gray-900` |
| Section title | `text-lg font-semibold text-gray-900` |
| Body | `text-sm text-gray-700` |
| Secondary | `text-sm text-gray-500` |
| Label | `text-sm font-medium text-gray-700` |
| Code/URL | `font-mono text-sm` |

Use Traditional Chinese consistently in user-facing strings.

## Color Roles

| Role | Tailwind family |
| --- | --- |
| Primary action | blue |
| Success / active | green |
| Warning / pending | yellow or amber |
| Error / destructive | red |
| Neutral / inactive | gray |
| LINE-related accent | green |

Status text should include a label in addition to color.

## Buttons

Primary:

```html
<button class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
  Save
</button>
```

Secondary:

```html
<button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
  Cancel
</button>
```

Destructive:

```html
<button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
  Delete
</button>
```

Icon-only buttons require an accessible label through visible text, `aria-label` or title.

## Forms

Standard input:

```html
<input class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500" />
```

Form requirements:

- visible label
- required indicator where relevant
- inline validation message
- preserved input after recoverable failure
- disabled submit state while request is running
- server validation message shown without exposing raw exception text

Credentials such as LINE tokens should never be redisplayed in full. Use masked placeholders and only send changed values.

## Tables

Desktop table container:

```html
<div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
  <table class="min-w-full divide-y divide-gray-200">
    ...
  </table>
</div>
```

Guidelines:

- headers use concise labels
- row actions are grouped consistently
- pending/destructive status can use subtle row background
- pagination and empty states remain inside the table card
- mobile views should use cards or horizontal scroll intentionally

Reusable components:

- `DataTable.vue`
- `StatusTag.vue`
- `ActionButtons.vue`

Use them when their API fits. Do not force a generic component around page-specific behavior.

## Status Labels

Reservation:

| State | Label | Color |
| --- | --- | --- |
| `pending` | 待確認 | yellow |
| `confirmed` | 已確認 | green or blue |
| `cancelled` | 已取消 | red or gray |
| `completed` | 已完成 | green |

Check-in:

| State | Label | Color |
| --- | --- | --- |
| waiting | 待報到 | gray |
| checked in | 已報到 | green |
| no show | 未到 | red |

Payment:

| State | Label | Color |
| --- | --- | --- |
| unpaid | 未付款 | gray |
| paid | 已付款 | green |

Always confirm actual backend enum values before adding a new label.

## Dialogs

Headless UI dialogs should include:

- `Dialog`
- `DialogPanel`
- `DialogTitle`
- focus management
- escape/overlay close behavior when safe
- explicit cancel and primary action

Do not close a dialog while a save request is in progress unless cancellation is implemented.

## Feedback

Use concise messages:

- success: action completed and what changed
- error: what failed and the next safe action
- warning: consequence before the action

Avoid browser `alert()` for new features. Existing pages still contain some legacy alerts; replace them with a shared notification pattern when those pages are modified.

## Responsive Behavior

Tailwind breakpoints:

| Prefix | Minimum width |
| --- | --- |
| `sm` | 640 px |
| `md` | 768 px |
| `lg` | 1024 px |
| `xl` | 1280 px |

Primary review widths:

- 375 px phone
- 768 px tablet
- 1280 px desktop

Navigation uses a fixed desktop sidebar and a Headless UI disclosure panel on mobile.

## Accessibility

- preserve visible focus rings
- associate labels and inputs
- provide alt text for meaningful images
- do not encode status only by color
- maintain logical heading order
- ensure interactive elements are buttons or links
- test keyboard operation of menus and dialogs
- keep touch targets approximately 40 px or larger

## Motion

Use short transitions, generally 150-300 ms. Motion should explain state changes, not delay routine administration.

Respect reduced-motion preferences when adding new animations.

## Review Checklist

- spacing follows a consistent parent layout
- heading hierarchy is clear
- actions use the correct semantic color
- loading/error/empty states exist
- mobile overflow is intentional
- focus is visible
- text contrast is sufficient
- destructive operations require confirmation
- credential fields remain masked
- no raw HTML is rendered without sanitization

Last reviewed: 2026-06-13
