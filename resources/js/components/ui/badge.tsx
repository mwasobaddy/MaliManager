import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * Badges, per v2 §6.8. Every variant is a soft fill plus a foreground token,
 * never a saturated one — a badge sits inside a table row and a row of solid
 * fills reads as noise.
 *
 * The `quiet` variant drops the fill entirely and keeps only the icon colour,
 * which §7.2 asks for on *normal* states: paid, occupied, active. Colour is
 * meant for exceptions, so the common case should be the quiet one.
 */
const badgeVariants = cva(
  "inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden whitespace-nowrap rounded-md border px-2 py-0.5 text-xs font-medium transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&>svg]:pointer-events-none [&>svg]:size-3",
  {
    variants: {
      variant: {
        /**
         * Normal states in dense tables (§6.8 "quiet"). Icon carries the
         * colour, the label stays `--text` — nothing shouts unless it must.
         */
        quiet: "border-transparent bg-transparent text-foreground",
        neutral:
          "border-transparent bg-status-neutral-bg text-status-neutral-fg",
        primary: "border-transparent bg-brand-primary-100 text-primary-text",
        accent: "border-transparent bg-brand-accent-100 text-accent-on-tint",
        success:
          "border-transparent bg-status-success-bg text-status-success-fg",
        warning:
          "border-transparent bg-status-warning-bg text-status-warning-fg",
        danger: "border-transparent bg-status-danger-bg text-status-danger-fg",
        info: "border-transparent bg-status-info-bg text-status-info-fg",

        /* Retained names, mapped onto the spec set. */
        secondary: "border-transparent bg-status-neutral-bg text-status-neutral-fg",
        destructive:
          "border-transparent bg-status-danger-bg text-status-danger-fg",
        outline:
          "border-border-strong bg-transparent text-foreground hover:bg-muted",
        default: "border-transparent bg-brand-primary-100 text-primary-text",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }