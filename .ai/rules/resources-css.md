---
paths:
    - resources/css/editor.css
---

# Resources Css

## Resize handle visibility selector targets container not img

Tiptap v3's resizable image renders via ResizableNodeView into DOM: [data-resize-container] > [data-resize-wrapper] > img + [data-resize-handle] children. ProseMirror's selectNode() puts 'ProseMirror-selectednode' on the NodeView ROOT (data-resize-container), NOT the <img>. So to show resize handles on selection, target '[data-resize-container].ProseMirror-selectednode [data-resize-handle]', never 'img.ProseMirror-selectednode ~ [data-resize-handle]' which never matches.
