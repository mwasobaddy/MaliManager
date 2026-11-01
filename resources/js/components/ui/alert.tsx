import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * Alerts, per v2 §6.5: a 4px left border in the semantic colour over that
 * colour's soft background, with the title in `--text` and the description in
 * `--text-muted`.
 *
 * Only `default` and `destructive` existed before, so a successful payment and
 * a failed one both rendered as the same neutral box and the colour lived in
 * whatever icon the caller passed in. The five semantic variants plus `tip`
 * let the call site name the meaning and have the surface carry it.
 *
 * `tip` is the accent variant (§5): teal, for helpful guidance rather than a
 * status. It is deliberately distinct from `info`, which is blue and means
 * system information.
 */
const alertVariants = cva(
  "relative grid w-full grid-cols-[0_1fr] items-start gap-y-0.5 rounded-lg border border-l-4 px-4 py-3 text-sm has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] has-[>svg]:gap-x-3 [&>svg]:size-4 [&>svg]:translate-y-0.5",
  {
    variants: {
      variant: {
        default: "bg-card text-foreground border-border",
        success:
          "bg-status-success-bg text-status-success-fg border-status-success [&>svg]:text-status-success",
        warning:
          "bg-status-warning-bg text-status-warning-fg border-status-warning [&>svg]:text-status-warning",
        danger:
          "bg-status-danger-bg text-status-danger-fg border-status-danger [&>svg]:text-status-danger",
        info: "bg-status-info-bg text-status-info-fg border-status-info [&>svg]:text-status-info",
        tip: "bg-brand-accent-100 text-accent-on-tint border-brand-accent [&>svg]:text-accent-text",
        /** Retained name; `danger` is the spec's word for it. */
        destructive:
          "bg-status-danger-bg text-status-danger-fg border-status-danger [&>svg]:text-status-danger",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Alert({
  className,
  variant,
  ...props
}: React.ComponentProps<"div"> & VariantProps<typeof alertVariants>) {
  return (
    <div
      data-slot="alert"
      /* `alert` interrupts; only the neutral and tip variants are advisory. */
      role={variant === "success" || variant === "info" || variant === "tip" ? "status" : "alert"}
      className={cn(alertVariants({ variant }), className)}
      {...props}
    />
  )
}

function AlertTitle({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-title"
      /* Title is `--text`, not the semantic colour (§6.5). */
      className={cn(
        "col-start-2 line-clamp-1 min-h-4 font-medium tracking-tight text-foreground",
        className
      )}
      {...props}
    />
  )
}

function AlertDescription({
  className,
  ...props
}: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="alert-description"
      className={cn(
        "text-muted-foreground col-start-2 grid justify-items-start gap-1 text-sm [&_p]:leading-relaxed",
        className
      )}
      {...props}
    />
  )
}

export { Alert, AlertTitle, AlertDescription, alertVariants }
