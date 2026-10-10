---
paths:
  - resources/css/editor.css
  - resources/css/app.css
---

# Resources Css

## Resize handle visibility selector targets container not img

Tiptap v3's resizable image renders via ResizableNodeView into DOM: [data-resize-container] > [data-resize-wrapper] > img + [data-resize-handle] children. ProseMirror's selectNode() puts 'ProseMirror-selectednode' on the NodeView ROOT (data-resize-container), NOT the <img>. So to show resize handles on selection, target '[data-resize-container].ProseMirror-selectednode [data-resize-handle]', never 'img.ProseMirror-selectednode ~ [data-resize-handle]' which never matches.

## --border-strong token now exists; card-surface inputs need a fill
--border-strong (#D8CFBF light / #4A443C dark) is now implemented and exposed as --color-border-strong; it was specified in ui-design-system.md §2.1/§2.2 and referenced in 8 places (data table headers, checkbox, pagination, scroll area, hover borders) but never defined. Use it for card-surface input borders and the data-table header divider. NOTE: shadcn Input is bg-transparent, which is fine on the page background but on a Card surface leaves the field defined by a ~1.25:1 border alone — use className="bg-background border-border-strong" for inputs sitting inside cards. Honest limit: the warm neutral palette tops out at ~1.65:1 against the card surface, below WCAG 1.4.11's 3:1 for UI boundaries; closing that gap needs --border-strong darkened, which is a palette decision.
