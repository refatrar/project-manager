import * as React from "react"

import { cn } from "@/lib/utils"

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        "border-input bg-card placeholder:text-subtle-foreground flex field-sizing-content min-h-20 max-h-[min(20rem,45dvh)] w-full rounded-md border px-3 py-2 text-base leading-relaxed shadow-xs transition-[color,border-color,box-shadow] duration-(--motion-fast) outline-none disabled:cursor-not-allowed disabled:bg-muted disabled:opacity-60 md:text-sm dark:bg-background/40",
        "focus-visible:border-ring focus-visible:ring-ring/25 focus-visible:ring-[3px]",
        "aria-invalid:border-destructive aria-invalid:ring-destructive/15 dark:aria-invalid:ring-destructive/30",
        className
      )}
      {...props}
    />
  )
}

export { Textarea }
