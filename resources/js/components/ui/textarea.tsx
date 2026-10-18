import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * Mirrors `Input` deliberately: same border, radius, transparent surface,
 * shadow, focus ring, invalid state and disabled treatment, so a form reads as
 * one row of controls rather than a mix.
 *
 * Two details are load-bearing rather than cosmetic:
 *
 * - `text-base md:text-sm`. Below the `md` breakpoint the control is 16px.
 *   iOS Safari zooms the viewport when it focuses an input under 16px, and it
 *   does not zoom back out until the field loses focus — which strands users
 *   mid-form. `Input` does this; a textarea must too or it becomes the one
 *   control on the page that breaks it.
 * - `min-h-16` and `py-2`. A single-line box is `h-9` with `py-1`; a
 *   multi-line one needs the extra leading to keep its first line off the
 *   border.
 */
function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "border-input bg-card placeholder:text-muted-foreground flex min-h-16 w-full rounded-md border px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none hover:border-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-bg-disabled disabled:opacity-100 md:text-sm",
        "aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }