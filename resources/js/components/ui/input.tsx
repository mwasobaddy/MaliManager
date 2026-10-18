import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        /*
         * §6.2. The border token is `--border-input`, which the base layer
         * points at `#8F8576`; it was `--border` at about 1.2:1, which is not
         * a visible edge for anyone. The surface is opaque rather than
         * transparent so an input sitting on a card's top-lit gradient reads
         * as a control rather than a hole.
         */
        "border-input bg-card file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium hover:border-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-bg-disabled disabled:opacity-100 md:text-sm",
        "focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]",
        "aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
        className
      )}
      {...props}
    />
  )
}

export { Input }
