import * as React from "react"

import { cn } from "@/lib/utils"

function Card({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card"
      className={cn(
        "bg-card text-card-foreground relative flex flex-col gap-6 rounded-xl border py-6 shadow-[inset_0_1px_0_0_rgb(255_255_255_/_0.55),0_1px_2px_rgb(42_38_33_/_0.04),0_4px_12px_rgb(42_38_33_/_0.06)] bg-[radial-gradient(120%_60%_at_50%_0%,rgb(255_255_255_/_0.45),transparent_70%),linear-gradient(180deg,var(--card-hi)_0%,var(--card)_38%)] dark:bg-[radial-gradient(120%_60%_at_50%_0%,rgb(255_255_255_/_0.05),transparent_70%),linear-gradient(180deg,var(--card-hi)_0%,var(--card)_45%)] dark:shadow-[inset_0_1px_0_0_rgb(255_255_255_/_0.06),0_1px_2px_rgb(0_0_0_/_0.3),0_6px_18px_rgb(0_0_0_/_0.35)]",
        className
      )}
      {...props}
    />
  )
}

function CardHeader({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-header"
      className={cn("flex flex-col gap-1.5 px-6", className)}
      {...props}
    />
  )
}

function CardTitle({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-title"
      className={cn("leading-none font-semibold", className)}
      {...props}
    />
  )
}

function CardDescription({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-description"
      className={cn("text-muted-foreground text-sm", className)}
      {...props}
    />
  )
}

function CardContent({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-content"
      className={cn("px-6", className)}
      {...props}
    />
  )
}

function CardFooter({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="card-footer"
      className={cn("flex items-center px-6", className)}
      {...props}
    />
  )
}

export { Card, CardHeader, CardFooter, CardTitle, CardDescription, CardContent }
