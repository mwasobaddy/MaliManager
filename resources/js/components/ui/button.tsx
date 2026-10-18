import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-[color,box-shadow] disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground shadow-xs hover:bg-primary/90",
        /**
         * Stock shadcn's default button: the neutral fill, not a colour. We
         * repointed `--primary` at the brand terracotta, so `default` now means
         * "the primary action" and this is the neutral chrome for dismiss
         * controls (Cancel, the overlay close button). Deliberately not
         * `secondary`: same look today, different intent, so they can diverge.
         */
        shadcndefault:
          "bg-background border-border-strong text-foreground shadow-xs hover:bg-muted",
        destructive:
          "bg-action-danger text-on-danger shadow-xs hover:brightness-95 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        /** §6.1 Danger ghost — opens a confirmation, does not destroy. */
        "danger-ghost":
          "text-status-danger hover:bg-status-danger-bg focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        outline:
          "border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground",
        /**
         * §6.1 Accent outline — secondary-but-important actions that are not
         * destructive: Export, Send reminder, Add note. Teal because they lead
         * somewhere; terracotta `default` is reserved for what you do.
         */
        accent:
          "border border-accent-text bg-transparent text-accent-text shadow-xs hover:bg-brand-accent-100 hover:text-accent-on-tint focus-visible:ring-accent-text/40",
        /** §6.1 Accent solid — rare. Upgrade, "Try the new reports". */
        "accent-solid":
          "bg-brand-accent text-on-accent shadow-xs hover:bg-brand-accent/90 focus-visible:ring-brand-accent/40",
        secondary:
          "bg-secondary text-secondary-foreground shadow-xs hover:bg-secondary/80",
        ghost: "hover:bg-accent hover:text-accent-foreground",
        /** §6.1 Link — accent, underlined. Was `text-primary` (terracotta). */
        link: "text-accent-text underline-offset-4 hover:text-accent-text-hover hover:underline",
      },
      size: {
        default: "h-9 px-4 py-2 has-[>svg]:px-3",
        sm: "h-8 rounded-md px-3 has-[>svg]:px-2.5",
        lg: "h-10 rounded-md px-6 has-[>svg]:px-4",
        icon: "size-9",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
