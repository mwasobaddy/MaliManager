---
paths:
    - 'resources/js/lib/editor/**'
---

# Editor

## Spread this.parent?.() in addCommands overrides

When overriding addCommands() on an extended Tiptap extension, you MUST spread ...this.parent?.() to preserve inherited commands. LayoutImage (extends Image) dropped setImage by returning only its own setImageLayout, so editor.chain().setImage() threw 'setImage is not a function' and uploaded images never inserted. Always merge parent commands.
