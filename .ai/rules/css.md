---
paths:
  - 'resources/js/components/rich-text-editor.tsx, resources/css/editor.css'
---

# Css

## RichTextEditor drives dark-mode-safe Word-like formatting
resources/js/components/rich-text-editor.tsx is the shared TipTap v3 editor for agreement templates and occupant leases (API: name/value/onChange/placeholder; renders a hidden input with the sanitized HTML). Extensions are pinned to @tiptap/*@3.30.3. Images upload via fetch('/editor-images') JSON (per resources/js JSON rule), NOT base64. Dark-mode text is fixed in resources/css/editor.css by overriding prose's --tw-prose-* variables (e.g. --tw-prose-body:var(--foreground)) on .md-editor__content .prose. Import editor.css with a relative/../../ path (the @/ alias maps to resources/js, not resources/css).
